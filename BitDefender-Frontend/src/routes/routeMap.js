import Home from '../pages/Home';
import Dashboard from '../pages/Dashboard';
import Profile from '../pages/Profile';
import Login from '../pages/Login';
import Events from '../pages/Events';

export const publicRoutes = [
  {
    path: '/login',
    component: Login,
  },
];

export const privateRoutes = [
  {
    path: '/',
    component: Home,
  },
  {
    path: '/dashboard',
    component: Dashboard,
  },
  {
    path: '/profile',
    component: Profile,
  },
  {
    path: '/events',
    component: Events,
  },
]; 