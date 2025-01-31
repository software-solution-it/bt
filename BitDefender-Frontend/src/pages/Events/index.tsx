import React, { useEffect, useState } from 'react';
import { Card, Table, Tag, Input, Space, message, Select, Tooltip, Typography } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { format } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import { syncService } from '../../services/sync.service';
import { NetworkInventoryItem } from '../../interfaces/MachineList';
import { ErrorBoundary } from 'react-error-boundary';
import ResponsiveTable from '../../components/ResponsiveTable';
import './styles.css';

const { Text } = Typography;

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
    errorCode?: number;
    errorMessage?: string;
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
      
      // Vamos remover duplicatas usando o id do evento
      const allEvents = endpoints.flatMap(endpoint => endpoint.webhook_events || []);
      const uniqueEvents = allEvents.filter((event, index, self) =>
        index === self.findIndex((e) => e.id === event.id)
      );
      
      setEvents(uniqueEvents);
    } catch (error) {
      console.error('Erro ao carregar eventos:', error);
    } finally {
      setLoading(false);
    }
  };

  const getSeverityColor = (severity: string) => {
    const colors = {
      high: 'red',
      medium: 'orange',
      low: 'green',
      info: 'blue'
    };
    return colors[severity] || 'default';
  };

  const getEventTypeTag = (type: string) => {
    const types = {
      'task-status': { color: 'blue', text: 'Status de Tarefa' },
      'av': { color: 'red', text: 'Antivírus' },
      'aph': { color: 'purple', text: 'Anti-Phishing' },
      'fw': { color: 'orange', text: 'Firewall' },
      'avc': { color: 'cyan', text: 'Controle Avançado' },
      'uc': { color: 'gold', text: 'Controle de Usuário' },
      'dp': { color: 'lime', text: 'Proteção de Dados' },
      'hd': { color: 'magenta', text: 'HyperDetect' },
      'sva-load': { color: 'volcano', text: 'Carga do Servidor' },
      'exchange-malware': { color: 'red', text: 'Exchange Malware' },
      'network-sandboxing': { color: 'geekblue', text: 'Sandbox' },
      'adcloud': { color: 'blue', text: 'AD Cloud' },
      'exchange-user-credentials': { color: 'purple', text: 'Exchange Credenciais' },
      'modules': { color: 'green', text: 'Módulos' },
      'sva': { color: 'orange', text: 'Servidor de Segurança' },
      'registration': { color: 'cyan', text: 'Registro' },
      'supa-update-status': { color: 'blue', text: 'Status de Atualização' }
    };

    // Se não encontrar no mapeamento, usa o module do event_data
    return types[type] || { color: 'default', text: type };
  };

  const getTaskStatus = (status: number) => {
    const statusMap = {
      1: { text: 'Pendente', color: 'orange' },
      2: { text: 'Em Progresso', color: 'blue' },
      3: { text: 'Finalizado', color: 'green' }
    };
    return statusMap[status] || { text: 'Desconhecido', color: 'default' };
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
      title: 'Data/Hora',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 180,
      render: (date) => {
        if (!date) return '-';
        const [datePart] = date.split('.');
        return format(new Date(datePart), 'dd/MM/yyyy HH:mm', { locale: ptBR });
      },
      fixed: 'left',
    },
    {
      title: 'Máquina',
      key: 'machine',
      width: 200,
      fixed: 'left',
      render: (_, record) => (
        <Tooltip title={`IP: ${record.computer_ip}`}>
          <Text>{record.computer_name}</Text>
        </Tooltip>
      ),
    },
    {
      title: 'Tipo',
      dataIndex: 'event_type',
      key: 'event_type',
      width: 150,
      render: (type, record) => {
        const eventType = getEventTypeTag(type);
        // Usa o module do event_data se disponível
        const displayText = record.event_data?.module ? 
          record.event_data.module.toUpperCase() : 
          eventType.text;
        
        return <Tag color={eventType.color}>{displayText}</Tag>;
      },
    },
    {
      title: 'Nome da Tarefa',
      key: 'task_name',
      width: 250,
      ellipsis: true,
      render: (_, record) => (
        <Tooltip title={record.event_data?.taskName}>
          <span>{record.event_data?.taskName || '-'}</span>
        </Tooltip>
      ),
    },
    {
      title: 'Status',
      key: 'status',
      width: 150,
      render: (_, record) => {
        const eventData = record.event_data;
        if (record.event_type === 'task-status') {
          // Se tem erro, considera como falha
          if (eventData.errorCode > 0) {
            return <Tag color="red">Falha</Tag>;
          }
          const status = getTaskStatus(eventData.status);
          return <Tag color={status.color}>{status.text}</Tag>;
        }
        return '-';
      },
    },
    {
      title: 'Mensagem de Erro',
      key: 'error_message',
      width: 300,
      ellipsis: true,
      render: (_, record) => {
        const eventData = record.event_data;
        if (eventData.errorCode > 0) {
          return (
            <Tooltip title={eventData.errorMessage}>
              <Text type="danger" ellipsis>
                {eventData.errorMessage || `Erro ${eventData.errorCode}`}
              </Text>
            </Tooltip>
          );
        }
        return '-';
      },
    },
    {
      title: 'Severidade',
      dataIndex: 'severity',
      key: 'severity',
      width: 120,
      render: (severity) => (
        <Tag color={getSeverityColor(severity)}>
          {severity.toUpperCase()}
        </Tag>
      ),
    },
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