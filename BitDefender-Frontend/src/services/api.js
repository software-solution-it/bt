import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8080',
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true, // Importante para CORS com credenciais
  timeout: 10000,
});

// Interceptor para adicionar o token em todas as requisições
api.interceptors.request.use((config) => {
  // Se for uma rota de autenticação, não faz nada
  if (config.url.includes('/auth/')) {
    return config;
  }

  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
}, (error) => {
  return Promise.reject(error);
});

// Interceptor para tratar erros de resposta
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.code === 'ECONNABORTED') {
      console.error('Timeout da requisição');
      return Promise.reject(new Error('Tempo limite da requisição excedido'));
    }

    if (!error.response) {
      console.error('Erro de rede:', error);
      return Promise.reject(new Error('Erro de conexão com o servidor'));
    }

    // Se for erro de autenticação e não for uma rota de auth
    if (error.response?.status === 401 && !error.config.url.includes('/auth/')) {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      window.location.href = '/login';
      return Promise.reject(new Error('Sessão expirada'));
    }

    if (error.response?.status === 403) {
      return Promise.reject(new Error('Acesso não autorizado'));
    }

    return Promise.reject(error.response?.data?.message || error.message || 'Erro desconhecido');
  }
);

export default api; 