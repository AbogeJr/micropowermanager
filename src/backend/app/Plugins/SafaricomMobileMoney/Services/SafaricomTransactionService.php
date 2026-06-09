<?php

declare(strict_types=1);

namespace App\Plugins\SafaricomMobileMoney\Services;

use App\Jobs\ProcessPayment;
use App\Models\Address\Address;
use App\Models\Meter\Meter;
use App\Models\SolarHomeSystem;
use App\Models\Transaction\Transaction;
use App\Plugins\SafaricomMobileMoney\Models\SafaricomTransaction;
use App\Services\AbstractPaymentAggregatorTransactionService;
use App\Services\DeviceService;
use App\Services\Interfaces\IBaseService;
use App\Services\Interfaces\PaymentInitializer;
use App\Services\PersonService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

/**
 * @implements IBaseService<SafaricomTransaction>
 */
class SafaricomTransactionService extends AbstractPaymentAggregatorTransactionService implements IBaseService, PaymentInitializer {
    public function __construct(
        private Meter $meter,
        private Address $address,
        private Transaction $transaction,
        private SafaricomTransaction $safaricomTransaction,
        private SafaricomCredentialService $credentialService,
        private SafaricomAuthService $authService,
    ) {
        parent::__construct(
            $this->meter,
            $this->address,
            $this->transaction,
            $this->safaricomTransaction,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function initializeTransactionData(): array {
        return [
            'order_id' => Uuid::uuid4()->toString(),
            'reference_id' => Uuid::uuid4()->toString(),
            'serial_id' => $this->getSerialId(),
            'status' => SafaricomTransaction::STATUS_REQUESTED,
            'currency' => 'KES',
            'customer_id' => $this->getCustomerId(),
            'amount' => $this->getAmount(),
            'metadata' => [
                'serial_id' => $this->getSerialId(),
                'customer_id' => $this->getCustomerId(),
            ],
        ];
    }

    public function getByOrderId(string $orderId): ?SafaricomTransaction {
        return $this->safaricomTransaction->newQuery()->where('order_id', '=', $orderId)->first();
    }

    public function getByReferenceId(string $referenceId): ?SafaricomTransaction {
        return $this->safaricomTransaction->newQuery()->where('reference_id', '=', $referenceId)->first();
    }

    public function getByCheckoutRequestId(string $checkoutRequestId): ?SafaricomTransaction {
        return $this->safaricomTransaction->newQuery()->where('checkout_request_id', '=', $checkoutRequestId)->first();
    }

    public function getByMpesaReceipt(string $receipt): ?SafaricomTransaction {
        return $this->safaricomTransaction->newQuery()->where('mpesa_receipt_number', '=', $receipt)->first();
    }

    /**
     * @return Collection<int, SafaricomTransaction>
     */
    public function getByStatus(int $status): Collection {
        return $this->safaricomTransaction->newQuery()->where('status', '=', $status)->get();
    }

    public function getById(int $id): ?SafaricomTransaction {
        return $this->safaricomTransaction->newQuery()->find($id);
    }

    public function getAll(?int $limit = null): Collection|LengthAwarePaginator {
        $query = $this->safaricomTransaction->newQuery();
        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update($safaricomTransaction, array $data): SafaricomTransaction {
        $safaricomTransaction->update($data);
        $safaricomTransaction->fresh();

        return $safaricomTransaction;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): SafaricomTransaction {
        return $this->safaricomTransaction->newQuery()->create($data);
    }

    public function delete($safaricomTransaction): ?bool {
        return $safaricomTransaction->delete();
    }

    public function getSafaricomTransaction(): SafaricomTransaction {
        return $this->getPaymentAggregatorTransaction();
    }

    public function getSerialId(): ?string {
        return $this->getMeterSerialNumber();
    }

    public function processSuccessfulPayment(int $companyId, SafaricomTransaction $transaction): void {
        $id = $transaction->transaction->id;
        dispatch(new ProcessPayment($companyId, $id));
        $transaction->setStatus(SafaricomTransaction::STATUS_SUCCESS);
        $transaction->save();
    }

    public function processFailedPayment(SafaricomTransaction $transaction): void {
        $transaction->setStatus(SafaricomTransaction::STATUS_FAILED);
        $transaction->save();
    }

    /**
     * Initiate an STK Push for `$sender` (the customer's phone number).
     * Creates a SafaricomTransaction + core Transaction in one DB transaction,
     * then issues the STK Push so PesaPal-style atomicity holds even when
     * Daraja errors out (we don't end up with orphaned transaction rows).
     *
     * @return array{transaction: Transaction, provider_data: array<string, mixed>}
     */
    public function initializePayment(
        float $amount,
        string $sender,
        string $message,
        string $type,
        int $customerId,
        ?string $serialId = null,
    ): array {
        $deviceType = null;
        if ($serialId !== null) {
            $device = app(DeviceService::class)->getBySerialNumber($serialId);
            $deviceType = $device?->device_type;
        }

        $credential = $this->credentialService->getCredentials();

        try {
            DB::connection('tenant')->beginTransaction();

            /** @var SafaricomTransaction $safaricomTxn */
            $safaricomTxn = $this->safaricomTransaction->newQuery()->create([
                'amount' => $amount,
                'currency' => 'KES',
                'order_id' => Uuid::uuid4()->toString(),
                'reference_id' => Uuid::uuid4()->toString(),
                'status' => SafaricomTransaction::STATUS_REQUESTED,
                'customer_id' => $customerId,
                'serial_id' => $serialId,
                'device_type' => $deviceType,
                'phone_number' => $sender,
                'account_reference' => $serialId,
                'transaction_desc' => $message ?: 'MPM Payment',
                'metadata' => [
                    'customer_id' => $customerId,
                    'serial_id' => $serialId,
                    'transaction_type' => $type,
                ],
            ]);

            /** @var Transaction $transaction */
            $transaction = $safaricomTxn->transaction()->create([
                'amount' => $amount,
                'sender' => $sender,
                'message' => $message,
                'type' => $type,
            ]);

            $result = $this->sendStkPush($safaricomTxn, $credential);
            if ($result['error'] !== null) {
                throw new \RuntimeException('Safaricom STK Push failed: '.$result['error']);
            }

            $safaricomTxn->setCheckoutRequestId((string) $result['checkout_request_id']);
            $safaricomTxn->setMerchantRequestId((string) $result['merchant_request_id']);
            $safaricomTxn->update(['response_data' => $result['raw']]);

            DB::connection('tenant')->commit();

            return [
                'transaction' => $transaction,
                'provider_data' => [
                    'reference_id' => $safaricomTxn->getReferenceId(),
                    'checkout_request_id' => $safaricomTxn->getCheckoutRequestId(),
                    'merchant_request_id' => $safaricomTxn->getMerchantRequestId(),
                    'customer_message' => $result['customer_message'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        }
    }

    /**
     * Apply a Safaricom result code to a transaction.
     * Used by both the STK Push result webhook and the C2B confirmation
     * webhook so failed/cancelled payments never leave a transaction stuck
     * in REQUESTED. Driven server-side; we never trust the inbound payload
     * blindly.
     *
     * @param array<string, mixed> $payload
     */
    public function applyResultCode(SafaricomTransaction $transaction, int $resultCode, array $payload, int $companyId): void {
        if (!empty($payload['mpesa_receipt'])) {
            $transaction->setMpesaReceiptNumber((string) $payload['mpesa_receipt']);
            $transaction->setExternalTransactionId((string) $payload['mpesa_receipt']);
        }
        if (!empty($payload['transaction_date'])) {
            $transaction->transaction_date = $payload['transaction_date'];
        }
        $existingResponse = $transaction->response_data ?? [];
        $transaction->response_data = array_merge(is_array($existingResponse) ? $existingResponse : [], $payload);
        $transaction->save();

        match ($resultCode) {
            0 => $this->processSuccessfulPayment($companyId, $transaction),
            // 1032 = user cancelled, 1037 = timeout, 1 = insufficient funds, etc.
            default => $this->processFailedPayment($transaction),
        };
    }

    /**
     * @return array{checkout_request_id: ?string, merchant_request_id: ?string, customer_message: ?string, raw: array<string, mixed>, error: ?string}
     */
    private function sendStkPush(SafaricomTransaction $transaction, \App\Plugins\SafaricomMobileMoney\Models\SafaricomCredential $credential): array {
        $timestamp = date('YmdHis');
        $shortcode = $credential->getShortcode();
        $password = base64_encode($shortcode.$credential->getPasskey().$timestamp);

        $callbackUrl = $credential->getResultUrl() ?: $this->buildDefaultResultUrl();

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) round($transaction->getAmount()),
            'PartyA' => $transaction->getPhoneNumber(),
            'PartyB' => $shortcode,
            'PhoneNumber' => $transaction->getPhoneNumber(),
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $transaction->getDeviceSerial() ?: $transaction->getReferenceId(),
            'TransactionDesc' => $transaction->transaction_desc ?: 'MPM Payment',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->authService->getAccessToken(),
                'Content-Type' => 'application/json',
            ])->post($this->stkPushUrl($credential), $payload);
        } catch (\Throwable $e) {
            Log::error('Safaricom STK Push exception', [
                'reference_id' => $transaction->getReferenceId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'checkout_request_id' => null,
                'merchant_request_id' => null,
                'customer_message' => null,
                'raw' => [],
                'error' => $e->getMessage(),
            ];
        }

        $body = $response->json();
        if (!$response->successful() || !is_array($body)) {
            return [
                'checkout_request_id' => null,
                'merchant_request_id' => null,
                'customer_message' => null,
                'raw' => is_array($body) ? $body : ['raw' => $response->body()],
                'error' => 'Daraja returned HTTP '.$response->status().': '.$response->body(),
            ];
        }

        // ResponseCode "0" = accepted by Daraja (push delivered). Anything else
        // is a synchronous failure.
        $responseCode = (string) ($body['ResponseCode'] ?? '');
        if ($responseCode !== '0') {
            return [
                'checkout_request_id' => $body['CheckoutRequestID'] ?? null,
                'merchant_request_id' => $body['MerchantRequestID'] ?? null,
                'customer_message' => $body['CustomerMessage'] ?? $body['ResponseDescription'] ?? null,
                'raw' => $body,
                'error' => $body['errorMessage'] ?? $body['ResponseDescription'] ?? 'Daraja rejected the STK Push',
            ];
        }

        return [
            'checkout_request_id' => $body['CheckoutRequestID'] ?? null,
            'merchant_request_id' => $body['MerchantRequestID'] ?? null,
            'customer_message' => $body['CustomerMessage'] ?? null,
            'raw' => $body,
            'error' => null,
        ];
    }

    private function stkPushUrl(\App\Plugins\SafaricomMobileMoney\Models\SafaricomCredential $credential): string {
        $base = $credential->isProduction()
            ? (string) config('safaricom-mobile-money.api.production_url', 'https://api.safaricom.co.ke')
            : (string) config('safaricom-mobile-money.api.sandbox_url', 'https://sandbox.safaricom.co.ke');

        return $base.'/mpesa/stkpush/v1/processrequest';
    }

    private function buildDefaultResultUrl(): string {
        $companyId = request()->attributes->get('companyId');
        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl === '' || !is_int($companyId)) {
            return '';
        }

        return $appUrl.'/api/safaricom/webhook/stk-push-result/'.$companyId;
    }

    public function validateMeterSerial(string $serialId): bool {
        return $this->meter->newQuery()
            ->where('serial_number', $serialId)
            ->where('in_use', 1)
            ->exists();
    }

    public function validateSHSSerial(string $serialId): bool {
        return app()->make(SolarHomeSystem::class)
            ->newQuery()
            ->where('serial_number', $serialId)
            ->exists();
    }

    public function getCustomerPhoneByCustomerId(int $customerId): ?string {
        try {
            $person = app()->make(PersonService::class)->getById($customerId);

            return (string) $person->addresses->first()->phone;
        } catch (\Exception) {
            return null;
        }
    }
}
