import { MachineListResponse } from '../interfaces/MachineList';
import api from './api';

interface EnhancedError extends Error {
  originalError?: any;
}

/**
 * Handles API error responses
 * @param {Error} error - The error object
 * @param {string} operation - The operation description
 * @throws {Error} Enhanced error with better description
 */
const handleApiError = (error: any, operation: string) => {
  const errorMessage = error.response?.data?.message || error.message;
  const enhancedError: EnhancedError = new Error(`Erro ao ${operation}: ${errorMessage}`);
  enhancedError.originalError = error;
  throw enhancedError;
};

export const syncService = {

  // Método para buscar as chaves API
  async getApiKeys() {
    try {
      const { data } = await api.post('/api-keys/list', {
        jsonrpc: "2.0",
        method: "getApiKeys",
        params: {},
        id: 1
      });
      return data?.result || [];
    } catch (error) {
      handleApiError(error, 'buscar chaves API');
    }
  },

  // Método para buscar o inventário de máquinas
  async getMachineInventory(apiKeyId: number, type?: string): Promise<MachineListResponse> {
    try {
      const { data } = await api.post('/machines/inventory', {
        jsonrpc: "2.0",
        method: "getMachineInventory",
        params: {
          api_key_id: apiKeyId,
          ...(type && { type })
        },
        id: 1
      });
      return data;
    } catch (error) {
      handleApiError(error, 'buscar inventário de máquinas');
      throw error;
    }
  },
}


export default syncService;