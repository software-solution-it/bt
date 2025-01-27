import { useAuth } from '../../contexts/AuthContext';

export default function Home() {
  const { user } = useAuth();

  return (
    <div>
      <h1>Bem-vindo, {user?.name}!</h1>
    </div>
  );
} 