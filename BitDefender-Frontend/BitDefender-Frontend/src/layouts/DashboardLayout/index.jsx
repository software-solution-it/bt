import { useState, useEffect } from 'react';
import { MdMenu, MdClose } from 'react-icons/md';
import SideMenu from '../../components/SideMenu';
import './styles.css';

export default function DashboardLayout({ children }) {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [isOverlayVisible, setIsOverlayVisible] = useState(false);

  const toggleMenu = () => {
    setIsMenuOpen(!isMenuOpen);
  };

  useEffect(() => {
    if (isMenuOpen) {
      setTimeout(() => setIsOverlayVisible(true), 50);
    } else {
      setIsOverlayVisible(false);
    }
  }, [isMenuOpen]);

  return (
    <div className="dashboard-layout">
      {/* Botão flutuante que aparece apenas quando o menu está fechado */}
      {!isMenuOpen && (
        <button 
          className="menu-toggle floating"
          onClick={toggleMenu}
          aria-label="Open menu"
        >
          <MdMenu size={24} />
        </button>
      )}

      <aside className={`sidebar ${isMenuOpen ? 'open' : ''}`}>
        <div className="logo-container">
          <div className="logo">BitDefender</div>
          {isMenuOpen && (
            <button 
              className="menu-toggle"
              onClick={toggleMenu}
              aria-label="Close menu"
            >
              <MdClose size={24} />
            </button>
          )}
        </div>
        <SideMenu isMenuOpen={isMenuOpen} onToggleMenu={toggleMenu} />
      </aside>

      <main className="main-content">
        <div className="dashboard-content">
          {children}
        </div>
      </main>

      {isMenuOpen && (
        <div 
          className={`mobile-overlay ${isOverlayVisible ? 'visible' : ''}`}
          onClick={toggleMenu}
        />
      )}
    </div>
  );
} 