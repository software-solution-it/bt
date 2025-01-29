async listKeys() {
  try {
    const response = await api.get('/apikeys/list');
    return response.data;
  } catch (error) {
    console.error('Error listing API keys:', error);
    throw error;
  }
},

async createKey(data) {
  try {
    const response = await api.post('/apikeys/create', data);
    return response.data;
  } catch (error) {
    console.error('Error creating API key:', error);
    throw error;
  }
},

async updateKey(id, data) {
  try {
    const response = await api.put(`/apikeys/update/${id}`, data);
    return response.data;
  } catch (error) {
    console.error('Error updating API key:', error);
    throw error;
  }
},

async deleteKey(id) {
  try {
    const response = await api.delete(`/apikeys/delete/${id}`);
    return response.data;
  } catch (error) {
    console.error('Error deleting API key:', error);
    throw error;
  }
} 