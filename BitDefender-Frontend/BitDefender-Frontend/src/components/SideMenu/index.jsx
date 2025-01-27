import { useNavigate, useLocation } from 'react-router-dom';
import { 
  MdDashboard,
  MdPeople,
  MdVpnKey,
  MdComputer,
  MdNotifications
} from 'react-icons/md';
import './styles.css';

export default function SideMenu({ isMenuOpen, onToggleMenu }) {
  const navigate = useNavigate();
  const location = useLocation();

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
      </ul>
    </nav>
  );
} 