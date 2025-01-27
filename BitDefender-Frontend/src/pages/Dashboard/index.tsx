import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { 
  DesktopOutlined, 
  CheckCircleOutlined,
  WarningOutlined,
  SyncOutlined,
  CloseCircleOutlined,
  SafetyCertificateOutlined,
  KeyOutlined,
  UnlockOutlined
} from '@ant-design/icons';
import { syncService } from '../../services/sync.service';
import { MachineListResponse, NetworkInventoryItem, License, Machine } from '../../interfaces/MachineList';
import './styles.css';
import React from 'react';
import { Pie, Bar } from 'react-chartjs-2';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  ArcElement,
  Tooltip,
  Legend
} from 'chart.js';

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  ArcElement,
  Tooltip,
  Legend
);

interface DashboardData {
  totalEndpoints: number;
  totalLicenses: number;
  usedLicenses: number;
  availableLicenses: number;
  endpoints: Array<{
    endpoint_id: string;
    name: string;
    group_id: string;
    group_name: string;
    is_managed: number;
    is_deleted: number;
    status: string;
    ip_address: string;
    mac_address: string;
    operating_system: string;
    operating_system_version: string;
    label: string;
    last_seen: string;
    machine_type: number;
    company_id: string;
    policy_id: string;
    policy_name: string;
    policy_applied: number;
    malware_status: string;
    agent_info: string;
    state: number;
    modules: string;
    move_state: number;
    managed_with_best: number;
    risk_score: string | null;
    fqdn: string;
    macs: string;
    ssid: string | null;
    created_at: string;
    updated_at: string;
  }>;
}

export default function Dashboard() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [apiKeys, setApiKeys] = useState<Array<{ id: number; name: string }>>([]);
  const [selectedApiKey, setSelectedApiKey] = useState<number | null>(null);
  const [dashboardData, setDashboardData] = useState<DashboardData>({
    totalEndpoints: 0,
    totalLicenses: 0,
    usedLicenses: 0,
    availableLicenses: 0,
    endpoints: []
  });
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [selectedMachine, setSelectedMachine] = useState<any>(null);
  const navigate = useNavigate();

  useEffect(() => {
    fetchApiKeys();
  }, []);

  useEffect(() => {
    if (selectedApiKey) {
      fetchDashboardData();
    }
  }, [selectedApiKey]);

  const fetchApiKeys = async () => {
    try {
      const keys = await syncService.getApiKeys();
      setApiKeys(keys);
      if (keys.length > 0) {
        setSelectedApiKey(keys[0].id);
      }
    } catch (error) {
      console.error('Erro ao carregar chaves API', error);
    }
  };

  const fetchDashboardData = async () => {
    if (!selectedApiKey) return;

    try {
      setLoading(true);
      
      // Busca endpoints
      const endpointsResponse = await syncService.getMachineInventory(selectedApiKey, 'endpoints');
      const endpoints = endpointsResponse.result.items || [];
      
      // Busca licenças
      const licensesResponse = await syncService.getMachineInventory(selectedApiKey, 'licenses');
      const licenses = licensesResponse.result.items || [];
      const license = licenses[0] || { total_slots: 0, used_slots: 0 };

      setDashboardData({
        totalEndpoints: endpoints.length,
        totalLicenses: license.total_slots,
        usedLicenses: license.used_slots,
        availableLicenses: license.total_slots - license.used_slots,
        endpoints: endpoints
      });

    } catch (error: any) {
      setError(error.message);
      console.error('Erro ao carregar dados do dashboard', error);
    } finally {
      setLoading(false);
    }
  };

  const getStatusText = (state: number) => {
    switch (state) {
      case 1:
        return 'Online';
      case 0:
        return 'Offline';
      default:
        return 'Desconhecido';
    }
  };

  const getStatusColor = (state: number) => {
    switch (state) {
      case 1:
        return '#52c41a'; // green
      case 0:
        return '#ff4d4f'; // red
      default:
        return '#d9d9d9'; // grey
    }
  };

  const showMachineDetails = (machine: any) => {
    setSelectedMachine(machine);
    setIsModalVisible(true);
  };

  const getModuleDisplayName = (key: string) => {
    const moduleNames: { [key: string]: string } = {
      antimalware: 'Antimalware',
      firewall: 'Firewall',
      contentControl: 'Controle de Conteúdo',
      powerUser: 'Usuário Avançado',
      deviceControl: 'Controle de Dispositivos',
      advancedThreatControl: 'Controle de Ameaças Avançado',
      applicationControl: 'Controle de Aplicações',
      encryption: 'Criptografia',
      networkAttackDefense: 'Defesa contra Ataques de Rede',
      antiTampering: 'Anti-Tampering',
      advancedAntiExploit: 'Anti-Exploit Avançado',
      userControl: 'Controle de Usuário',
      antiphishing: 'Anti-phishing',
      trafficScan: 'Varredura de Tráfego',
      edrSensor: 'Sensor EDR',
      hyperDetect: 'HyperDetect',
      remoteEnginesScanning: 'Varredura de Engines Remotas',
      sandboxAnalyzer: 'Analisador Sandbox',
      riskManagement: 'Gerenciamento de Risco'
    };

    return moduleNames[key] || key;
  };

  const handleViewDetails = (endpoint: any) => {
    navigate(`/machine/${endpoint.endpoint_id}`, { 
      state: { machineData: endpoint }
    });
  };

  const styles = {
    dashboardContainer: {
      padding: '32px',
      background: 'linear-gradient(135deg, #f6f8fc 0%, #f0f2f5 100%)',
      minHeight: '100vh',
    },
    dashboardHeader: {
      background: 'white',
      padding: '24px 32px',
      borderRadius: '16px',
      marginBottom: '32px',
      boxShadow: '0 4px 20px rgba(0,0,0,0.08)',
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center',
      transition: 'all 0.3s ease',
      '&:hover': {
        boxShadow: '0 6px 24px rgba(0,0,0,0.12)',
      },
    },
    statsCard: {
      borderRadius: '16px',
      padding: '24px',
      background: 'white',
      boxShadow: '0 4px 12px rgba(0,0,0,0.05)',
      transition: 'all 0.3s ease',
      border: 'none',
      overflow: 'hidden',
      position: 'relative' as const,
    } as const,
    iconWrapper: {
      width: '48px',
      height: '48px',
      borderRadius: '12px',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      marginBottom: '16px',
    },
    modalSection: {
      marginBottom: '24px',
      padding: '20px',
      background: '#f8f9fa',
      borderRadius: '8px',
    } as const,
    moduleItem: {
      display: 'flex',
      alignItems: 'center',
      padding: '12px',
      background: '#fff',
      borderRadius: '6px',
      boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
      gap: '12px',
    } as const,
    moduleGrid: {
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fill, minmax(250px, 1fr))',
      gap: '16px',
      padding: '16px',
      background: 'white',
      borderRadius: '8px',
      boxShadow: 'inset 0 2px 4px rgba(0,0,0,0.05)',
    } as const,
  };

  // Add hover effect via onMouseEnter/onMouseLeave
  const onMouseEnter = (e: React.MouseEvent<HTMLDivElement>) => {
    e.currentTarget.style.transform = 'translateY(-5px)';
    e.currentTarget.style.boxShadow = '0 8px 24px rgba(0,0,0,0.12)';
  };

  const onMouseLeave = (e: React.MouseEvent<HTMLDivElement>) => {
    e.currentTarget.style.transform = '';
    e.currentTarget.style.boxShadow = '';
  };

  const pieChartData = {
    labels: ['Online', 'Offline', 'Suspenso'],
    datasets: [{
      data: [
        dashboardData.endpoints.filter(e => e.status === 1).length,
        dashboardData.endpoints.filter(e => e.status === 0).length,
        dashboardData.endpoints.filter(e => e.status === 2).length,
      ],
      backgroundColor: ['#2e7d32', '#c62828', '#f57c00'],
    }]
  };

  const barChartData = {
    labels: ['Windows', 'Linux', 'Mac'],
    datasets: [{
      label: 'Sistemas Operacionais',
      data: [
        dashboardData.endpoints.filter(e => e.operating_system.includes('Windows')).length,
        dashboardData.endpoints.filter(e => e.operating_system.includes('Linux')).length,
        dashboardData.endpoints.filter(e => e.operating_system.includes('Mac')).length,
      ],
      backgroundColor: '#1a73e8',
    }]
  };

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: true,
    aspectRatio: 2,
    plugins: {
      legend: {
        position: 'bottom' as const
      }
    }
  };

  if (loading) {
    return (
      <div className="loading-container">
        <div className="loading-spinner"></div>
      </div>
    );
  }

  return (
    <div className="dashboard-wrapper">
      <div className="dashboard-header">
        <div className="header-title">
          <h1>Dashboard</h1>
        </div>
        <div className="header-controls">
          <select 
            className="api-select"
            value={String(selectedApiKey || '')}
            onChange={(e) => {
              setSelectedApiKey(Number(e.target.value));
              fetchDashboardData();
            }}
          >
            <option value="" disabled>Selecione uma chave API</option>
            {apiKeys.map(key => (
              <option key={key.id} value={String(key.id)}>
                {key.name}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="dashboard-stats">
        <div className="stat-card">
          <div className="stat-icon endpoints">
            <DesktopOutlined />
          </div>
          <div className="stat-info">
            <span className="stat-label">Total de Máquinas</span>
            <span className="stat-value">{dashboardData.totalEndpoints}</span>
          </div>
        </div>
        
        <div className="stat-card">
          <div className="stat-icon licenses">
            <KeyOutlined />
          </div>
          <div className="stat-info">
            <span className="stat-label">Licenças Totais</span>
            <span className="stat-value">{dashboardData.totalLicenses}</span>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon used">
            <UnlockOutlined />
          </div>
          <div className="stat-info">
            <span className="stat-label">Licenças Utilizadas</span>
            <span className="stat-value">{dashboardData.usedLicenses}</span>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon available">
            <SafetyCertificateOutlined />
          </div>
          <div className="stat-info">
            <span className="stat-label">Licenças Disponíveis</span>
            <span className="stat-value">{dashboardData.availableLicenses}</span>
          </div>
        </div>
      </div>

      <div className="charts-container">
        <div className="chart-card">
          <h3>Distribuição por Sistema Operacional</h3>
          <Bar data={barChartData} options={chartOptions} />
        </div>
        <div className="chart-card">
          <h3>Status dos Endpoints</h3>
          <Pie data={pieChartData} options={chartOptions} />
        </div>
      </div>

      <div className="dashboard-content">
        <div className="content-header">
          <h2>Lista de Máquinas</h2>
        </div>
        
        <div className="table-container">
          <table className="data-table">
            <thead>
              <tr>
                <th>Nome</th>
                <th>Label</th>
                <th>Status</th>
                <th>IP</th>
                <th>Sistema Operacional</th>
                <th>Política</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              {dashboardData.endpoints.map(endpoint => (
                <tr key={endpoint.endpoint_id}>
                  <td>{endpoint.name}</td>
                  <td>{endpoint.label || '-'}</td>
                  <td>
                    <span className={`status-badge ${getStatusText(endpoint.state).toLowerCase()}`}>
                      {getStatusText(endpoint.state)}
                    </span>
                  </td>
                  <td>{endpoint.ip_address}</td>
                  <td>{endpoint.operating_system}</td>
                  <td>
                    <span className={`policy-badge ${endpoint.policy_applied ? 'applied' : 'pending'}`}>
                      {endpoint.policy_name}
                    </span>
                  </td>
                  <td>
                    <button 
                      className="action-button"
                      onClick={() => handleViewDetails(endpoint)}
                    >
                      Detalhes
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
