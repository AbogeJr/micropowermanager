<?php

declare(strict_types=1);

namespace App\Plugins\SafaricomMobileMoney\Http\Controllers;

use App\Plugins\SafaricomMobileMoney\Http\Requests\SafaricomSTKPushRequest;
use App\Plugins\SafaricomMobileMoney\Http\Resources\SafaricomTransactionResource;
use App\Plugins\SafaricomMobileMoney\Models\SafaricomTransaction;
use App\Plugins\SafaricomMobileMoney\Services\SafaricomTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SafaricomTransactionController extends Controller {
    public function __construct(
        private SafaricomTransactionService $transactionService,
    ) {}

    public function getTransactions(Request $request): JsonResponse {
        $perPage = $request->integer('per_page', 15);

        return response()->json($this->transactionService->getAll($perPage));
    }

    /**
     * @return SafaricomTransactionResource|JsonResponse
     */
    public function getTransaction(int $id) {
        $transaction = $this->transactionService->getById($id);
        if (!$transaction instanceof SafaricomTransaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        return SafaricomTransactionResource::make($transaction);
    }

    public function initiateStkPush(SafaricomSTKPushRequest $request): JsonResponse {
        $data = $request->validated();
        $customerId = (int) ($data['customer_id'] ?? 0);
        $serialId = $data['device_serial'] ?? ($data['serial_id'] ?? null);

        try {
            $result = $this->transactionService->initializePayment(
                amount: (float) $data['amount'],
                sender: (string) $data['phone_number'],
                message: (string) ($data['account_reference'] ?? $serialId ?? 'Payment'),
                type: (string) ($data['type'] ?? 'energy'),
                customerId: $customerId,
                serialId: $serialId,
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'reference_id' => $result['provider_data']['reference_id'],
                'checkout_request_id' => $result['provider_data']['checkout_request_id'],
                'merchant_request_id' => $result['provider_data']['merchant_request_id'],
                'customer_message' => $result['provider_data']['customer_message'],
            ],
        ]);
    }
}
