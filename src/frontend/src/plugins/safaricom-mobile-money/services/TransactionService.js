import { Paginator } from "@/Helpers/Paginator.js"
import Client from "@/repositories/Client/AxiosClient.js"

const resource = `/api/safaricom`

export class TransactionService {
  constructor() {
    this.list = []
    this.paginator = new Paginator(`/api/safaricom/transactions`)
  }

  updateList(data) {
    this.list = []
    if (Array.isArray(data)) {
      this.list = data
    }
  }

  async getTransactions() {
    try {
      return await Client.get(`${resource}/transactions`)
    } catch (error) {
      console.error("Error fetching Safaricom transactions:", error)
      throw error
    }
  }

  async getTransaction(id) {
    try {
      return await Client.get(`${resource}/transactions/${id}`)
    } catch (error) {
      console.error("Error fetching Safaricom transaction:", error)
      throw error
    }
  }

  async initiateStkPush(payload) {
    try {
      return await Client.post(`${resource}/stk-push`, payload)
    } catch (error) {
      console.error("Error initiating STK Push:", error)
      throw error
    }
  }
}
