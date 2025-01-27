import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import DashboardLayout from '../layouts/DashboardLayout';
import Login from '../pages/Login';
import Dashboard from '../pages/Dashboard';
import Accounts from '../pages/Accounts';
import Companies from '../pages/Companies';
import Incidents from '../pages/Incidents';
import Integrations from '../pages/Integrations';
import Licenses from '../pages/Licenses';
import Machines from '../pages/Machines';
import Networks from '../pages/Networks';
import Packages from '../pages/Packages';
import Policies from '../pages/Policies';
import PushSettings from '../pages/PushSettings';
import Quarantine from '../pages/Quarantine';
import Reports from '../pages/Reports';
import MachineDetails from '../pages/MachineDetails';

const PrivateRoute = ({ children }) => {
  const { isAuthenticated } = useAuth();
  return isAuthenticated ? children : <Navigate to="/login" />;
};

const PrivatePageWrapper = ({ component: Component }) => (
  <PrivateRoute>
    <DashboardLayout>
      <Component />
    </DashboardLayout>
  </PrivateRoute>
);

export default function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      
      <Route path="/" element={<PrivatePageWrapper component={Dashboard} />} />
      <Route path="/dashboard" element={<PrivatePageWrapper component={Dashboard} />} />
      <Route path="/accounts" element={<PrivatePageWrapper component={Accounts} />} />
      <Route path="/companies" element={<PrivatePageWrapper component={Companies} />} />
      <Route path="/incidents" element={<PrivatePageWrapper component={Incidents} />} />
      <Route path="/integrations" element={<PrivatePageWrapper component={Integrations} />} />
      <Route path="/licenses" element={<PrivatePageWrapper component={Licenses} />} />
      <Route path="/machines" element={<PrivatePageWrapper component={Machines} />} />
      <Route path="/networks" element={<PrivatePageWrapper component={Networks} />} />
      <Route path="/packages" element={<PrivatePageWrapper component={Packages} />} />
      <Route path="/policies" element={<PrivatePageWrapper component={Policies} />} />
      <Route path="/push-settings" element={<PrivatePageWrapper component={PushSettings} />} />
      <Route path="/quarantine" element={<PrivatePageWrapper component={Quarantine} />} />
      <Route path="/reports" element={<PrivatePageWrapper component={Reports} />} />
      <Route path="/machine/:id" element={<PrivatePageWrapper component={MachineDetails} />} />

      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  );
} 