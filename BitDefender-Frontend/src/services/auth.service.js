import api from './api';
import { jwtDecode } from 'jwt-decode';

export const authService = {
  async login(credentials) {
    try {
      const response = await api.post('/auth/login', {
        params: {
          email: credentials.email,
          password: credentials.password
        }
      });
      const result = response.data.result;
      if (result.success) {
        // Armazena o token no localStorage
        localStorage.setItem('auth_token', result.data.token);
        return {
          success: true,
          data: result.data
        };
      } else {
        return {
          success: false,
          error: result.error || 'Erro ao fazer login'
        };
      }
    } catch (error) {
      console.error('Login error:', error);
      return {
        success: false,
        error: error.response?.data?.result?.error || 
          error.message || 
          'Erro de conexão com o servidor'
      };
    }
  },

  async logout() {
    try {
      const response = await api.post('/auth/logout');
      
      // Remove o token do localStorage
      localStorage.removeItem('auth_token');
      
      // Remove o token do header das requisições
      delete api.defaults.headers.common['Authorization'];

      if (response.data.result.success) {
        return true;
      } else {
        throw new Error(response.data.result.error || 'Erro ao fazer logout');
      }
    } catch (error) {
      console.error('Logout error:', error);
      // Mesmo com erro no backend, limpa os dados locais
      localStorage.removeItem('auth_token');
      delete api.defaults.headers.common['Authorization'];
      
      throw new Error(
        error.response?.data?.result?.error || 
        error.message || 
        'Erro ao fazer logout'
      );
    }
  },

  async getProfile() {
    try {
      const token = localStorage.getItem('auth_token');
      if (!token) {
        throw new Error('Não autenticado');
      }
      return {
        success: true,
        user: jwtDecode(token).user
      };
    } catch (error) {
      console.error('Get profile error:', error);
      throw error;
    }
  }
}; 