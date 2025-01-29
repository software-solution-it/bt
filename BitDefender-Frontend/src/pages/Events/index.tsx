import React, { useEffect, useState } from 'react';
import { Card, Table, Tag, Input, Space, message, Select } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { format } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import { syncService } from '../../services/sync.service';
import { NetworkInventoryItem } from '../../interfaces/MachineList';
import { ErrorBoundary } from 'react-error-boundary';
import ResponsiveTable from '../../components/ResponsiveTable';

interface WebhookEvent {
  id: number;
  status: string;
  severity: string;
  created_at: string;
  event_type: string;
  computer_name: string;
  computer_ip: string;
  event_data: {
    module: string;
    malware_name?: string;
    file_path?: string;
    taskName?: string;
    isSuccessful?: number;
    policy_name?: string;
    scan_type?: string;
  };
} 

interface ApiKey {
  id: number;
  name: string;
}

const { Search } = Input;

// Componente de fallback para erros
function ErrorFallback({error}: {error: Error}) {
  return (
    <Card>
      <div role="alert">
        <h2>Algo deu errado:</h2>
        <pre style={{ color: 'red' }}>{error.message}</pre>
      </div>
    </Card>
  );
}

export default function Events() {
  const [events, setEvents] = useState<WebhookEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchText, setSearchText] = useState('');
  const [selectedSeverity, setSelectedSeverity] = useState<string>('');
  const [selectedType, setSelectedType] = useState<string>('');

  const eventTypeMap: {[key: string]: string} = {
    'installation': 'Instalação',
    'antimalware': 'Antimalware',
    'av': 'Antimalware',
    'updater': 'Atualizador',
    'policy': 'Política de Segurança',
    'hd': 'HyperDetect',
    'dp': 'Proteção de Dados',
    'modules': 'Módulos',
    'registration': 'Registro',
    'fw': 'Firewall',
    'uc': 'Controle de Acesso',
    'network-sandboxing': 'Sandbox'
  };

  const severityOptions = [
    { value: '', label: 'Selecione a Severidade' },
    { value: 'high', label: 'Alta' },
    { value: 'medium', label: 'Média' },
    { value: 'low', label: 'Baixa' },
    { value: 'info', label: 'Info' }
  ];

  const eventTypeOptions = [
    { value: '', label: 'Selecione o Evento' },
    ...Object.entries(eventTypeMap).map(([value, label]) => ({
      value,
      label
    }))
  ];

  useEffect(() => {
    const savedApiKey = sessionStorage.getItem('selectedApiKey');
    if (savedApiKey) {
      fetchEvents();
    }
  }, []);

  const fetchEvents = async () => {
    const savedApiKey = sessionStorage.getItem('selectedApiKey');
    if (!savedApiKey) {
      return;
    }

    try {
      setLoading(true);
      const response = await syncService.getMachineInventory(Number(savedApiKey), 'endpoints');
      const endpoints = response.result.items || [];
      
      // Extrai eventos de todos os endpoints
      const allEvents = endpoints.flatMap(endpoint => endpoint.webhook_events || []);
      setEvents(allEvents);
    } catch (error) {
      console.error('Erro ao carregar eventos:', error);
    } finally {
      setLoading(false);
    }
  };

  const getSeverityColor = (severity: string) => {
    switch ((severity || 'default').toLowerCase()) {
      case 'high':
        return 'red';
      case 'medium':
        return 'orange';
      case 'low':
        return 'green';
      default:
        return 'default';
    }
  };

  const filteredEvents = events.filter(event => {
    try {
      // Verifica se o evento é válido
      if (!event) return false;

      // Busca por nome da máquina
      const computerName = (event.computer_name || '').toLowerCase();
      const searchTermLower = (searchText || '').toLowerCase();
      const matchesSearch = !searchText || computerName.includes(searchTermLower);

      // Filtro de severidade
      const eventSeverity = (event.severity || '').toLowerCase();
      const selectedSeverityLower = (selectedSeverity || '').toLowerCase();
      const matchesSeverity = !selectedSeverity || eventSeverity === selectedSeverityLower;

      // Filtro de tipo
      const matchesType = !selectedType || 
        event.event_type === selectedType || 
        (selectedType === 'antimalware' && event.event_type === 'av');

      return matchesSearch && matchesSeverity && matchesType;
    } catch (error) {
      console.error('Erro ao filtrar evento:', error);
      return false;
    }
  });

  const columns: ColumnsType<WebhookEvent> = [
    {
      title: 'Data',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (date) => {
        if (!date) return '-';
        const [datePart] = date.split('.');
        return format(new Date(datePart), 'dd/MM/yyyy HH:mm', { locale: ptBR });
      }
    },
    {
      title: 'Computador',
      dataIndex: 'computer_name',
      key: 'computer_name',
    },
    {
      title: 'IP',
      dataIndex: 'computer_ip',
      key: 'computer_ip',
    },
    {
      title: 'Tipo',
      dataIndex: 'event_type',
      key: 'event_type',
      render: (type) => eventTypeMap[type] || type
    },
    {
      title: 'Severidade',
      dataIndex: 'severity',
      key: 'severity',
      render: (severity) => (
        <Tag color={getSeverityColor(severity)}>
          {(severity || 'N/A').toUpperCase()}
        </Tag>
      )
    },
    {
      title: 'Detalhes',
      dataIndex: 'event_data',
      key: 'event_data',
      width: 300,
      render: (data) => {
        // Função para formatar detalhes do evento
        const formatEventDetails = () => {
          // Mapeamento de tipos de eventos comuns
          const eventTypeMap: {[key: string]: string} = {
            'installation': 'Instalação',
            'antimalware': 'Antimalware',
            'updater': 'Atualizador',
            'policy': 'Política de Segurança',
            'hd': 'HyperDetect',
            'dp': 'Proteção de Dados',
            'modules': 'Módulos',
            'registration': 'Registro'
          };

          // Mapeamento de status
          const statusMap: {[key: string]: string} = {
            'completed': 'instalação concluída',
            'detected': 'ameaça detectada',
            'applied': 'configurações aplicadas',
            'portscan_blocked': 'Bloqueado scan de porta',
            'data_protection_blocked': 'Bloqueado por proteção de dados',
            'uc_site_blocked': 'Site bloqueado',
            'avc_blocked': 'Bloqueado por AVC'
          };

          // Tratamento específico para HyperDetect
          if (data.module === 'hd') {
            return `HyperDetect: ${data.attack_type || 'Ameaça'} detectada (${data.detection_level || 'nível padrão'})`;
          }

          // Tratamento específico para Módulos
          if (data.module === 'modules') {
            if (typeof data.modules === 'object') {
              const changes = [];
              Object.entries(data.modules).forEach(([key, value]) => {
                const moduleNames: {[key: string]: string} = {
                  malware_status: 'Antimalware',
                  firewall_status: 'Firewall',
                  avc_status: 'AVC',
                  ids_status: 'IDS',
                  uc_web_filtering: 'Web Filtering',
                  dp_status: 'Data Protection'
                };
                if (moduleNames[key]) {
                  changes.push(`${moduleNames[key]}: ${value ? 'Ativado' : 'Desativado'}`);
                }
              });
              return changes.length > 0 ? 
                `Atualização de módulos:\n${changes.join('\n')}` : 
                'Atualização de configuração dos módulos';
            }
            return 'Atualização de configuração dos módulos';
          }

          // Tratamento específico para Registro
          if (data.module === 'registration') {
            return `Registro do produto: ${data.product_registration === 'registered' ? 'Ativado' : 'Desativado'}`;
          }

          // Formatação específica para cada tipo de evento
          if (data.module === 'installation' && data.status === 'completed') {
            return 'Instalação do agente BitDefender concluída';
          }
          
          if (data.module === 'updater' && data.status === 'completed') {
            return 'Atualização de assinaturas concluída';
          }
          
          if (data.module === 'policy' && data.status === 'applied') {
            return `Política de Segurança "${data.policy_name || 'Padrão'}" aplicada`;
          }
          
          if (data.module === 'antimalware' && data.status === 'detected') {
            return `Verificação de malware ${data.scan_type || ''} concluída`;
          }

          if (data.malware_name) {
            return `Malware: ${data.malware_name} em ${data.file_path}`;
          }
          if (data.taskName) {
            return `Tarefa: ${data.taskName} (${data.isSuccessful ? 'Concluída' : 'Falha'})`;
          }
          if (typeof data.modules === 'object') {
            const changes = [];
            Object.entries(data.modules).forEach(([key, value]) => {
              const moduleNames: {[key: string]: string} = {
                malware_status: 'Antimalware',
                firewall_status: 'Firewall',
                avc_status: 'AVC',
                ids_status: 'IDS',
                uc_web_filtering: 'Web Filtering',
                dp_status: 'Data Protection'
              };
              if (moduleNames[key]) {
                changes.push(`${moduleNames[key]}: ${value ? 'Ativado' : 'Desativado'}`);
              }
            });
            return `Atualização de módulos:\n${changes.join('\n')}`;
          }
          if (data.status) {
            return `${statusMap[data.status] || data.status}`;
          }
          if (data.threatType) {
            return `Ameaça detectada: ${data.threatType} em ${data.filePaths?.join(', ')}`;
          }
          // Tratamento para eventos comuns do sistema
          const eventType = eventTypeMap[data.module] || data.module;
          const status = statusMap[data.status] || data.status;
          return `${eventType}: ${status}`;
        };
        
        return (
          <div style={{ 
            whiteSpace: 'normal',
            wordBreak: 'break-word',
            maxWidth: '300px'
          }}>
            {formatEventDetails()}
          </div>
        );
      }
    }
  ];

  const mobileColumns = [
    {
      title: 'Data',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (date) => format(new Date(date), 'dd/MM/yy HH:mm')
    },
    {
      title: 'Tipo',
      dataIndex: 'event_type',
      key: 'event_type',
    },
    {
      title: 'Severidade',
      dataIndex: 'severity',
      key: 'severity',
      render: (severity) => (
        <Tag color={getSeverityColor(severity)}>
          {severity?.toUpperCase()}
        </Tag>
      )
    }
  ];

  return (
    <ErrorBoundary fallback={<ErrorFallback error={new Error('Ocorreu um erro ao carregar os eventos')} />}>
      <Card title="Eventos">
        <Space direction="vertical" style={{ width: '100%', marginBottom: 16 }}>
          <div style={{ display: 'flex', gap: 16 }}>
            <Select
              style={{ width: 200 }}
              placeholder="Severidade"
              allowClear
              value={selectedSeverity}
              onChange={setSelectedSeverity}
            >
              {severityOptions.map(option => (
                <Select.Option key={option.value} value={option.value}>
                  {option.label}
                </Select.Option>
              ))}
            </Select>

            <Select
              style={{ width: 200 }}
              placeholder="Tipo de Evento"
              allowClear
              value={selectedType}
              onChange={setSelectedType}
            >
              {eventTypeOptions.map(option => (
                <Select.Option key={option.value} value={option.value}>
                  {option.label}
                </Select.Option>
              ))}
            </Select>

            <Search
              placeholder="Buscar por máquina"
              allowClear
              style={{ width: 300 }}
              value={searchText}
              onChange={e => setSearchText(e.target.value)}
            />
          </div>
        </Space>

        <ResponsiveTable
          columns={columns}
          mobileColumns={mobileColumns}
          dataSource={filteredEvents}
          loading={loading}
          rowKey="id"
          pagination={{
            pageSize: 10,
            showSizeChanger: true,
            showTotal: (total) => `Total: ${total}`,
            responsive: true
          }}
        />
      </Card>
    </ErrorBoundary>
  );
}