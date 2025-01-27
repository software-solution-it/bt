import { BrowserRouter } from 'react-router-dom';
import { ConfigProvider } from 'antd';
import ptBR from 'antd/locale/pt_BR';
import { theme } from './theme/themeConfig';
import { AuthProvider } from './contexts/AuthContext';
import AppRoutes from './routes';

function App() {
  return (
    <ConfigProvider theme={theme} locale={ptBR}>
      <BrowserRouter>
        <AuthProvider>
          <AppRoutes />
        </AuthProvider>
      </BrowserRouter>
    </ConfigProvider>
  );
}

export default App;
