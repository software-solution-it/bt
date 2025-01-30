import api from './api';

export const webhookService = {
  async getEvents(apiKeyId: number) {
    try {
      const { data } = await api.get('/webhook', {
        params: { api_key_id: apiKeyId }
      });
      return data?.result?.items || [];
    } catch (error) {
      console.error('Error fetching webhook events:', error);
      throw error;
    }
  },

  async addEvents(events: any[]) {
    try {
      const { data } = await api.post('/webhook/addEvents', {
        events: events
      });
      return data?.result;
    } catch (error) {
      console.error('Error adding webhook events:', error);
      throw error;
    }
  }
};

export default webhookService; 