import { Routes, Route, Navigate } from 'react-router-dom';
import { PrivateRoute } from '../components/PrivateRoute';
import Login from '../pages/Login';
import Dashboard from '../pages/Dashboard';
import Events from '../pages/Events';
import Accounts from '../pages/Accounts';
import Licenses from '../pages/Licenses';
import DashboardLayout from '../layouts/DashboardLayout';
import Companies from '../pages/Companies';
import Incidents from '../pages/Incidents';
import Integrations from '../pages/Integrations';
import Machines from '../pages/Machines';
import Networks from '../pages/Networks';
import Packages from '../pages/Packages';
import Policies from '../pages/Policies';
import PushSettings from '../pages/PushSettings';
import Quarantine from '../pages/Quarantine';
import Reports from '../pages/Reports';
import MachineDetails from '../pages/MachineDetails';

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
      
      <Route path="/events" element={<PrivatePageWrapper component={Events} />} />
      
      <Route path="/accounts" element={<PrivatePageWrapper component={Accounts} />} />
      
      <Route path="/licenses" element={<PrivatePageWrapper component={Licenses} />} />

      <Route path="/companies" element={<PrivatePageWrapper component={Companies} />} />
      <Route path="/incidents" element={<PrivatePageWrapper component={Incidents} />} />
      <Route path="/integrations" element={<PrivatePageWrapper component={Integrations} />} />
      <Route path="/machines" element={<PrivatePageWrapper component={Machines} />} />
      <Route path="/networks" element={<PrivatePageWrapper component={Networks} />} />
      <Route path="/packages" element={<PrivatePageWrapper component={Packages} />} />
      <Route path="/policies" element={<PrivatePageWrapper component={Policies} />} />
      <Route path="/push-settings" element={<PrivatePageWrapper component={PushSettings} />} />
      <Route path="/quarantine" element={<PrivatePageWrapper component={Quarantine} />} />
      <Route path="/reports" element={<PrivatePageWrapper component={Reports} />} />
      <Route path="/machine/:id" element={<PrivatePageWrapper component={MachineDetails} />} />

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
} 