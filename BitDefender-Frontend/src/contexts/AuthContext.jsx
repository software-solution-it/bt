import { createContext, useContext, useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { message } from 'antd';
import { authService } from '../services/auth.service';

const AuthContext = createContext({});

export function AuthProvider({ children }) {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    checkAuth();
  }, []);

  const checkAuth = async () => {
    try {
      const token = localStorage.getItem('auth_token');
      if (token) {
        const profile = await authService.getProfile();
        if (profile.success) {
          setUser(profile.user);
          setIsAuthenticated(true);
        }
      }
    } catch (error) {
      console.error('Auth check failed:', error);
      setUser(null);
      setIsAuthenticated(false);
      localStorage.removeItem('auth_token');
    } finally {
      setLoading(false);
    }
  };

  const login = async (credentials) => {
    try {
      console.log('Tentando login com:', credentials);
      const response = await authService.login(credentials);
      console.log('Resposta do login:', response);
      
      if (response.success) {
        setUser(response.data.user);
        setIsAuthenticated(true);
        
        navigate('/dashboard');
        return { success: true };
      } else {
        return { 
          success: false, 
          error: response.error || 'Login falhou'
        };
      }
    } catch (error) {
      console.error('Erro no login:', error);
      setUser(null);
      setIsAuthenticated(false);
      return { 
        success: false, 
        error: typeof error === 'string' ? error : error.message || 'Erro no login'
      };
    }
  };

  const logout = async () => {
    try {
      await authService.logout();
      setUser(null);
      setIsAuthenticated(false);
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      localStorage.removeItem('auth_token');
      navigate('/login');
    } catch (error) {
      console.error('Logout error:', error);
    }
  };

  const value = {
    user,
    login,
    logout,
    isAuthenticated,
    loading
  };

  if (loading) {
    return <div>Carregando...</div>;
  }

  return (
    <AuthContext.Provider value={value}>
      {!loading && children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth deve ser usado dentro de um AuthProvider');
  }
  return context;
}