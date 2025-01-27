import { MOCK_USER } from '../config/mock';

export const authService = {
  async login(credentials) {
    try {
      // Simulando um delay para parecer mais real
      await new Promise(resolve => setTimeout(resolve, 500));

      if (credentials.email === MOCK_USER.email && credentials.password === MOCK_USER.password) {
        return {
          success: true,
          token: 'mock-jwt-token',
          user: {
            id: MOCK_USER.id,
            name: MOCK_USER.name,
            email: MOCK_USER.email,
            role: MOCK_USER.role
          }
        };
      }
      
      throw new Error('Credenciais inválidas');
    } catch (error) {
      console.error('Login error:', error);
      throw error;
    }
  },

  async logout() {
    return true;
  },

  async getProfile() {
    return {
      success: true,
      user: {
        id: MOCK_USER.id,
        name: MOCK_USER.name,
        email: MOCK_USER.email,
        role: MOCK_USER.role
      }
    };
  }
}; 