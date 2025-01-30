import api from './api';

export const syncService = {
  async getMachineInventory(apiKeyId, type = null) {
    try {
      const response = await api.post('/machines/inventory', {
        jsonrpc: '2.0',
        method: 'getMachineInventory',
        params: {
          api_key_id: apiKeyId,
          ...(type && { type })
        },
        id: null
      });
      return response.data;
    } catch (error) {
      console.error('Error fetching machine inventory:', error);
      throw error;
    }
  },

  async getApiKeys(type = 'all') {
    try {
      const response = await api.get('/apikeys/list', {
        params: { type }
      });
      
      console.log('API Keys Response:', response.data); // Debug
      return response.data.result || [];
      
    } catch (error) {
      console.error('Error fetching API keys:', error);
      throw error;
    }
  },

  async syncMachines() {
    try {
      const response = await api.post('/sync/all', {
        jsonrpc: '2.0',
        method: 'syncAll',
        params: {},
        id: null
      });
      return response.data.result;
    } catch (error) {
      console.error('Error syncing machines:', error);
      throw error;
    }
  }
};

export default syncService; 