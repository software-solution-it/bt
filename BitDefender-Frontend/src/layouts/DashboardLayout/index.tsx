import { useState, useEffect } from 'react';
import { Layout, Menu } from 'antd';
import {
  DashboardOutlined,
  DesktopOutlined,
  KeyOutlined,
  SettingOutlined,
  TeamOutlined,
  BellOutlined
} from '@ant-design/icons';
import { Link, useLocation } from 'react-router-dom';
import './styles.css';

const { Sider, Content } = Layout;

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  const [collapsed, setCollapsed] = useState(false);
  const [serviceType, setServiceType] = useState<string>('');
  const location = useLocation();

  useEffect(() => {
    // Recupera o tipo de serviço do sessionStorage
    const savedKey = sessionStorage.getItem('selectedApiKey');
    if (savedKey) {
      const keyType = sessionStorage.getItem('serviceType');
      setServiceType(keyType || '');
    }
  }, [location.pathname]);

  const getMenuItems = () => {
    const baseItems = [
      {
        key: '1',
        icon: <DashboardOutlined />,
        label: <Link to="/dashboard">Dashboard</Link>
      }
    ];

    // Menu para tipo Produtos
    if (serviceType === 'Produtos') {
      return [
        ...baseItems,
        {
          key: '2',
          icon: <KeyOutlined />,
          label: <Link to="/licenses">Licenças</Link>
        },
        {
          key: '3',
          icon: <SettingOutlined />,
          label: <Link to="/settings">Configurações</Link>
        }
      ];
    }

    // Menu para tipo Serviços
    return [
      ...baseItems,
      {
        key: '2',
        icon: <DesktopOutlined />,
        label: <Link to="/machines">Máquinas</Link>
      },
      {
        key: '3',
        icon: <BellOutlined />,
        label: <Link to="/events">Eventos</Link>
      },
      {
        key: '4',
        icon: <TeamOutlined />,
        label: <Link to="/users">Usuários</Link>
      },
      {
        key: '5',
        icon: <SettingOutlined />,
        label: <Link to="/settings">Configurações</Link>
      }
    ];
  };

  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Sider 
        collapsible 
        collapsed={collapsed} 
        onCollapse={value => setCollapsed(value)}
        className="dashboard-sider"
      >
        <div className="logo" />
        <Menu 
          theme="dark" 
          defaultSelectedKeys={['1']} 
          mode="inline"
          items={getMenuItems()}
          selectedKeys={[location.pathname]}
        />
      </Sider>
      <Layout>
        <Content style={{ margin: '0 16px' }}>
          {children}
        </Content>
      </Layout>
    </Layout>
  );
} 