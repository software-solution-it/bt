import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { 
  MdDashboard,
  MdPeople,
  MdVpnKey,
  MdComputer,
  MdNotifications,
  MdExitToApp
} from 'react-icons/md';
import { message } from 'antd';
import './styles.css';

export default function SideMenu({ isMenuOpen, onToggleMenu }) {
  const navigate = useNavigate();
  const location = useLocation();
  const { logout } = useAuth();

  const menuItems = [
    {
      key: 'dashboard',
      icon: <MdDashboard size={24} />,
      label: 'Dashboard',
      path: '/dashboard'
    },
    {
      key: 'accounts',
      icon: <MdPeople size={24} />,
      label: 'Contas',
      path: '/accounts'
    },
    {
      key: 'licenses',
      icon: <MdVpnKey size={24} />,
      label: 'Licenças',
      path: '/licenses'
    },
    {
      key: 'events',
      icon: <MdNotifications size={24} />,
      label: 'Eventos',
      path: '/events'
    }
  ];

  const handleNavigate = (path) => {
    navigate(path);
    onToggleMenu(); // Fecha o menu após navegar no mobile
  };

  const handleLogout = async () => {
    try {
      await logout();
      navigate('/login');
    } catch (error) {
    }
  };

  return (
    <nav className={`side-menu ${isMenuOpen ? 'open' : ''}`}>
      <ul>
        {menuItems.map((item) => (
          <li key={item.key}>
            <button
              className={`menu-item ${location.pathname === item.path ? 'active' : ''}`}
              onClick={() => handleNavigate(item.path)}
            >
              <span className="menu-icon">{item.icon}</span>
              <span className="menu-label">{item.label}</span>
            </button>
          </li>
        ))}
        <li className="logout-item">
          <button className="menu-item logout" onClick={handleLogout}>
            <span className="menu-icon">
              <MdExitToApp size={24} />
            </span>
            <span className="menu-label">Sair</span>
          </button>
        </li>
      </ul>
    </nav>
  );
} 