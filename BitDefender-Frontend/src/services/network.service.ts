import api from './api';

export const networkService = {
  async createScanTask(
    apiKeyId: number, 
    targetIds: string[], 
    name: string = 'Quick Scan',
    type: number = 1
  ) {
    try {
      const { data } = await api.post('/network/createScanTask', {
        jsonrpc: '2.0',
        method: 'createScanTask',
        params: {
          api_key_id: apiKeyId.toString(),
          targetIds: targetIds,
          type: type,
          name: name
        },
        id: crypto.randomUUID()
      });
      return data?.result;
    } catch (error) {
      console.error('Erro ao criar tarefa de scan:', error);
      throw error;
    }
  }
};

export default networkService; 