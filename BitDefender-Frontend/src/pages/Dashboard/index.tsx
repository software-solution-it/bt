import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  DesktopOutlined, 
  CheckCircleOutlined,
  WarningOutlined,
  SyncOutlined,
  CloseCircleOutlined,
  SafetyCertificateOutlined,
  KeyOutlined,
  UnlockOutlined,
  LoadingOutlined,
  ArrowUpOutlined
} from '@ant-design/icons';
import { Select, Spin } from 'antd';
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
import { format } from 'date-fns';
import { ptBR } from 'date-fns/locale';

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
  events: Array<{
    id: number;
    event_type: string;
    severity: string;
    created_at: string;
    event_data: any;
    module: string;
    computer_name?: string;
    computer_ip?: string;
  }>;
  securityEvents: Array<{
    id: number;
    event_type: string;
    severity: string;
    created_at: string;
    event_data: any;
    module: string;
    computer_name?: string;
    computer_ip?: string;
  }>;
}

export default function Dashboard() {
  const [isDataLoading, setIsDataLoading] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [apiKeys, setApiKeys] = useState<Array<{ id: number; name: string }>>([]);
  const [selectedApiKey, setSelectedApiKey] = useState<number | undefined>(() => {
    const savedKey = sessionStorage.getItem('selectedApiKey');
    return savedKey ? Number(savedKey) : undefined;
  });
  const [selectedKeyName, setSelectedKeyName] = useState<string>('');
  const initialDashboardData = {
    totalEndpoints: 0,
    totalLicenses: 0,
    usedLicenses: 0,
    availableLicenses: 0,
    endpoints: [],
    events: [],
    securityEvents: []
  };
  const [dashboardData, setDashboardData] = useState<DashboardData>({
    totalEndpoints: 0,
    totalLicenses: 0,
    usedLicenses: 0,
    availableLicenses: 0,
    endpoints: [],
    events: [],
    securityEvents: []
  });
  const [isModalVisible, setIsModalVisible] = useState(false);
  const [selectedMachine, setSelectedMachine] = useState<any>(null);
  const navigate = useNavigate();

  useEffect(() => {
    const initializeDashboard = async () => {
      try {
        setLoading(true);
        // Busca as chaves API
        const keys = await syncService.getApiKeys();
        setApiKeys(keys);

        // Verifica se existe uma chave salva
        const savedKey = sessionStorage.getItem('selectedApiKey');
        if (savedKey) {
          const selectedKey = keys.find(key => key.id === Number(savedKey));
          if (selectedKey) {
            setSelectedKeyName(selectedKey.name);
            setSelectedApiKey(selectedKey.id);
            // Busca os dados apenas se houver uma chave selecionada
            await fetchDashboardData(selectedKey.id);
          }
        }
      } catch (error) {
        console.error('Erro ao inicializar dashboard', error);
      } finally {
        setLoading(false);
      }
    };

    initializeDashboard();
  }, []);

  const fetchDashboardData = async (apiKeyId: number) => {
    if (!selectedApiKey) return;

    try {
      setIsDataLoading(true);
      
      // Busca endpoints
      const endpointsResponse = await syncService.getMachineInventory(apiKeyId, 'endpoints');
      const endpoints = endpointsResponse.result.items || [];
      
      // Busca licenças
      const licensesResponse = await syncService.getMachineInventory(apiKeyId, 'licenses');
      const licenses = licensesResponse.result.items || [];
      const license = licenses[0] || { total_slots: 0, used_slots: 0 };

      // Busca eventos
      const allEvents = endpoints.flatMap(endpoint => endpoint.webhook_events || []);
      console.log('Todos os eventos:', allEvents);
      
      const securityEvents = allEvents.filter(event => 
        event.event_type === 'av' || 
        event.severity.toLowerCase() === 'high' || 
        event.severity.toLowerCase() === 'medium'
      );
      console.log('Eventos de segurança:', securityEvents);

      setDashboardData({
        totalEndpoints: endpoints.length,
        totalLicenses: license.total_slots,
        usedLicenses: license.used_slots,
        availableLicenses: license.total_slots - license.used_slots,
        endpoints: endpoints,
        events: allEvents,
        securityEvents: securityEvents
      });

      // Logs para debug dos dados dos gráficos
      console.log('Eventos por tipo:', {
        antimalware: allEvents.filter(e => e.event_data.module === 'av').length,
        firewall: allEvents.filter(e => e.event_data.module === 'fw').length,
        controleAcesso: allEvents.filter(e => e.event_data.module === 'uc').length,
        protecaoDados: allEvents.filter(e => e.event_data.module === 'dp').length,
        hyperDetect: allEvents.filter(e => e.event_data.module === 'hd').length,
        sandbox: allEvents.filter(e => e.event_data.module === 'network-sandboxing').length,
        outros: allEvents.filter(e => !['av', 'fw', 'uc', 'dp', 'hd', 'network-sandboxing'].includes(e.event_data.module)).length
      });

      console.log('Eventos de segurança por severidade:', {
        alta: securityEvents.filter(e => e.severity?.toLowerCase() === 'high').length,
        media: securityEvents.filter(e => e.severity?.toLowerCase() === 'medium').length,
        baixa: securityEvents.filter(e => e.severity?.toLowerCase() === 'low').length
      });

    } catch (error: any) {
      setError(error.message);
      console.error('Erro ao carregar dados do dashboard', error);
    } finally {
      setIsDataLoading(false);
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

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: true,
    aspectRatio: 2,
    plugins: {
      legend: {
        position: 'bottom' as const,
        labels: {
          padding: 20,
          font: {
            size: 14
          }
        }
      },
      tooltip: {
        backgroundColor: 'rgba(0, 0, 0, 0.8)',
        padding: 16,
        titleFont: {
          size: 14,
          weight: 'bold' as const
        },
        bodyFont: {
          size: 13
        },
        bodySpacing: 8,
        displayColors: true,
        boxPadding: 6,
        callbacks: {
          title: function(context: any) {
            const type = context[0].label;
            return type;
          },
          label: function(context: any) {
            const value = context.raw;
            const dataset = context.dataset;
            
            if (context.datasetIndex === 0) { // Gráfico de tipos de evento
              const eventType = context.label.toLowerCase();
              const events = dashboardData.events.filter(e => {
                if (eventType === 'antimalware') return e.event_type === 'av';
                if (eventType === 'firewall') return e.event_type === 'fw';
                if (eventType === 'controle de acesso') return e.event_type === 'uc';
                if (eventType === 'proteção de dados') return e.event_type === 'dp';
                if (eventType === 'hyperdetect') return e.event_type === 'hd';
                if (eventType === 'sandbox') return e.event_type === 'network-sandboxing';
                if (eventType === 'task status') return e.event_type === 'task-status';
                if (eventType === 'módulos') return e.event_type === 'modules';
                if (eventType === 'registro') return e.event_type === 'registration';
                return !['av', 'fw', 'uc', 'dp', 'hd', 'network-sandboxing', 'task-status', 'modules', 'registration'].includes(e.event_type);
              });
              
              const details = events.map(e => ({
                computer: e.computer_name,
                ip: e.computer_ip,
                data: format(new Date(e.created_at.split('.')[0]), 'dd/MM/yyyy HH:mm', { locale: ptBR }),
                detalhes: formatEventDetails(e)
              }));
              
              return [
                'Últimos eventos:',
                '─────────────',
                ...details.slice(0, 3).map(d => 
                  `📅 ${d.data}\n💻 ${d.computer}\n🌐 ${d.ip}\n📝 ${d.detalhes}`
                )
              ];
            } else { // Gráfico de severidade
              const severity = context.label;
              const events = dashboardData.events.filter(e => 
                ['av', 'hd', 'network-sandboxing'].includes(e.event_data.module) &&
                e.severity?.toLowerCase() === severity.toLowerCase()
              );
              
              // Agrupa eventos por tipo
              const eventsByType = events.reduce((acc: any, event) => {
                const type = event.event_data.module;
                if (!acc[type]) acc[type] = 0;
                acc[type]++;
                return acc;
              }, {});
              
              // Formata o resumo dos tipos
              const typesSummary = Object.entries(eventsByType).map(([type, count]) => {
                const typeNames: {[key: string]: string} = {
                  'av': 'Antimalware',
                  'hd': 'HyperDetect',
                  'network-sandboxing': 'Sandbox'
                };
                return `${typeNames[type]}: ${count}`;
              });
              
              const details = events.map(e => ({
                computer: e.computer_name,
                ip: e.computer_ip,
                data: format(new Date(e.created_at.split('.')[0]), 'dd/MM/yyyy HH:mm', { locale: ptBR }),
                detalhes: formatEventDetails(e)
              }));
              
              return [
                `Total de eventos: ${events.length}`,
                '─────────────',
                ...typesSummary,
                '─────────────',
                'Últimos eventos:',
                ...details.slice(0, 3).map(d => 
                  `📅 ${d.data}\n💻 ${d.computer}\n🌐 ${d.ip}\n📝 ${d.detalhes}`
                )
              ];
            }
          }
        }
      }
    }
  };

  const formatEventDetails = (event: any) => {
    const eventData = event.event_data;

    if (eventData.malware_name) {
      return `Malware detectado: ${eventData.malware_name} (${eventData.malware_type || 'arquivo'})`;
    }

    if (eventData.taskName) {
      return `Tarefa executada: ${eventData.taskName} (${eventData.isSuccessful ? 'Sucesso' : 'Falha'})`;
    }

    if (event.event_type === 'modules') {
      const modules = [];
      if (eventData.malware_status) modules.push('Antimalware');
      if (eventData.firewall_status) modules.push('Firewall');
      if (eventData.avc_status) modules.push('AVC');
      return `Módulos atualizados: ${modules.join(', ')}`;
    }

    if (event.event_type === 'fw') {
      return `Firewall: ${eventData.status} (${eventData.protocol_id || 'TCP'})`;
    }

    if (event.event_type === 'uc') {
      return `Controle de Acesso: ${eventData.block_type} - ${eventData.url || 'URL bloqueada'}`;
    }

    if (event.event_type === 'dp') {
      return `Proteção de Dados: ${eventData.blocking_rule_name} (${eventData.target_type})`;
    }

    if (event.event_type === 'network-sandboxing') {
      return `Sandbox: ${eventData.threatType} detectado`;
    }

    if (eventData.module === 'registration') {
      return `Registro do produto: ${eventData.product_registration === 'registered' ? 'Ativado' : 'Desativado'}`;
    }

    return eventData.status || `Evento ${event.event_type}: ${event.severity}`;
  };

  const eventsChartData = {
    labels: [
      'Antimalware', 
      'Firewall', 
      'Controle de Acesso',
      'Proteção de Dados',
      'HyperDetect',
      'Sandbox',
      'Task Status',
      'Módulos',
      'Registro'
    ],
    datasets: [{
      label: 'Tipos de Eventos',
      data: [
        dashboardData.events?.filter(e => e.event_type === 'av').length || 0,
        dashboardData.events?.filter(e => e.event_type === 'fw').length || 0,
        dashboardData.events?.filter(e => e.event_type === 'uc').length || 0,
        dashboardData.events?.filter(e => e.event_type === 'dp').length || 0,
        dashboardData.events?.filter(e => e.event_type === 'hd').length || 0,
        dashboardData.events?.filter(e => e.event_type === 'network-sandboxing').length || 0,
        dashboardData.events?.filter(e => e.event_type === 'task-status').length || 0,
        dashboardData.events?.filter(e => e.event_type === 'modules').length || 0,
        dashboardData.events?.filter(e => e.event_type === 'registration').length || 0
      ],
      backgroundColor: ['#ff4d4f', '#1890ff', '#52c41a', '#faad14', '#722ed1', '#eb2f96', '#13c2c2', '#2f54eb', '#fa8c16'],
      hoverBackgroundColor: ['#ff7875', '#40a9ff', '#73d13d', '#ffc53d', '#9254de', '#f759ab', '#36cfc9', '#597ef7', '#ffa940'],
      borderWidth: 1,
      borderColor: '#fff'
    }]
  };

  const pieChartData = {
    labels: ['Alta', 'Média', 'Baixa'],
    datasets: [{
      data: [
        dashboardData.events?.filter(e => 
          ['av', 'hd', 'network-sandboxing'].includes(e.event_data.module) && 
          e.severity?.toLowerCase() === 'high'
        ).length || 0,
        dashboardData.events?.filter(e => 
          ['av', 'hd', 'network-sandboxing'].includes(e.event_data.module) && 
          e.severity?.toLowerCase() === 'medium'
        ).length || 0,
        dashboardData.events?.filter(e => 
          ['av', 'hd', 'network-sandboxing'].includes(e.event_data.module) && 
          e.severity?.toLowerCase() === 'low'
        ).length || 0,
      ],
      backgroundColor: ['#ff4d4f', '#faad14', '#52c41a'],
      hoverBackgroundColor: ['#ff7875', '#ffc53d', '#73d13d'],
      borderWidth: 1,
      borderColor: '#fff'
    }]
  };

  const LoadingOverlay = () => (
    <div
      style={{
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        background: 'rgba(255, 255, 255, 0.8)',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 1000,
        backdropFilter: 'blur(2px)'
      }}
    >
      <Spin 
        indicator={<LoadingOutlined style={{ fontSize: 40 }} spin />}
        tip="Carregando dados..."
        size="large"
      />
    </div>
  );

  const handleApiKeyChange = (option: { value: number, label: string } | null) => {
    if (!option) {
      sessionStorage.removeItem('selectedApiKey');
      setDashboardData(initialDashboardData);
      setSelectedApiKey(undefined);
      setSelectedKeyName('');
      return;
    }
    const value = option.value;
    setSelectedKeyName(option.label);
    setSelectedApiKey(value);
    sessionStorage.setItem('selectedApiKey', String(value));
    fetchDashboardData(value);
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
        <div style={{ position: 'relative' }}>
          <Select
            style={{ width: 200 }}
            placeholder="Selecione uma chave API"
            value={selectedApiKey ? { value: selectedApiKey, label: selectedKeyName } : undefined}
            labelInValue
            fieldNames={{ label: 'name', value: 'id' }}
            onChange={handleApiKeyChange}
            options={apiKeys}
          />
        </div>
      </div>

      {!selectedApiKey ? (
        <div style={{ 
          height: 'calc(100vh - 200px)', 
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center'
        }}>
          <div
            style={{ 
              textAlign: 'center',
              padding: '30px',
              background: '#f5f5f5',
              borderRadius: '8px',
              color: '#595959',
              boxShadow: '0 2px 8px rgba(0,0,0,0.05)',
              maxWidth: '500px',
              width: '100%'
            }}
          >
            <h2 style={{
              margin: 0,
              fontSize: '24px',
              fontWeight: 500
            }}>
              Selecione uma chave API para visualizar os dados
            </h2>
          </div>
        </div>
      ) : (
        <div
          style={{ position: 'relative' }}
        >
          {isDataLoading && <LoadingOverlay />}
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
              <h3>Distribuição de Eventos por Tipo</h3>
              <Bar data={eventsChartData} options={chartOptions} />
            </div>
            <div className="chart-card">
              <h3>Eventos de Segurança por Severidade</h3>
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
      )}
    </div>
  );
}
