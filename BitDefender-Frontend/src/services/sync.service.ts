import { MachineListResponse } from '../interfaces/MachineList';
import api from './api';

interface EnhancedError extends Error {
  originalError?: any;
}

export interface WebhookEvent {
  id: number;
  status: string;
  severity: string;
  created_at: string;
  event_data: {
    module: string;
    status?: number;
    taskName?: string;
    taskType?: number;
    hash?: string;
    file_path?: string;
    malware_name?: string;
    malware_type?: string;
    final_status?: string;
    [key: string]: any;
  };
  event_type: string;
  computer_ip: string;
  endpoint_id: string;
  processed_at: string | null;
  computer_name: string;
  error_message: string | null;
}

export interface MachineDetails {
  endpoint_id: string;
  name: string;
  group_id: string;
  api_key_id: number;
  is_managed: number;
  is_deleted: number;
  status: string;
  ip_address: string;
  mac_address: string;
  operating_system: string;
  operating_system_version: string;
  label: string;
  last_seen: string;
  machine_type: number;
  company_id: string;
  group_name: string;
  policy_id: string;
  policy_name: string;
  policy_applied: number;
  malware_status: string;
  agent_info: string;
  state: number;
  modules: string;
  move_state: number;
  managed_with_best: number;
  risk_score: string | null;
  fqdn: string;
  macs: string;
  ssid: string | null;
  created_at: string;
  updated_at: string;
  webhook_events: WebhookEvent[];
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

  async getEvents() {
    const response = await api.get('/sync/events');
    return response.data;
  },
}


export default syncService;