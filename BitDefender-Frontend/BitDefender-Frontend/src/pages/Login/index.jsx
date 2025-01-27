import { useState } from 'react';
import { useAuth } from '../../contexts/AuthContext';
import { isValidEmail } from '../../utils/validators';
import './styles.css';

export default function Login() {
  const { login } = useAuth();
  const [formData, setFormData] = useState({
    email: '',
    password: ''
  });
  const [errors, setErrors] = useState({});
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsLoading(true);
    
    try {
      const result = await login(formData);
      if (!result.success) {
        setErrors({ auth: 'Credenciais inválidas' });
      }
    } catch (error) {
      setErrors({ auth: error.message || 'Erro ao fazer login' });
    } finally {
      setIsLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
    // Limpa os erros quando o usuário começa a digitar
    setErrors({});
  };

  return (
    <div className="login-container">
      <div className="login-box">
        <div className="login-header">
          <h1>BitDefender</h1>
          <p>Bem-vindo</p>
        </div>
        
        <form onSubmit={handleSubmit} className="login-form">
          {errors.auth && (
            <div className="auth-error">
              {errors.auth}
            </div>
          )}
          
          <div className="form-group">
            <input
              type="email"
              name="email"
              value={formData.email}
              onChange={handleChange}
              placeholder="Email"
              className={errors.email ? 'error' : ''}
            />
          </div>

          <div className="form-group">
            <input
              type="password"
              name="password"
              value={formData.password}
              onChange={handleChange}
              placeholder="Senha"
              className={errors.password ? 'error' : ''}
            />
          </div>

          <button 
            type="submit" 
            className={`login-button ${isLoading ? 'loading' : ''}`}
            disabled={isLoading}
          >
            {isLoading ? (
              <div className="spinner"></div>
            ) : (
              'Entrar'
            )}
          </button>
        </form>
      </div>
    </div>
  );
} 