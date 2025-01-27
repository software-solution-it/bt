import { useEffect, useState } from 'react';
import { Table, Card, Button, Input, Space, Tag, Tooltip, message, Select } from 'antd';
import { SyncOutlined, SearchOutlined } from '@ant-design/icons';
import { syncService } from '../../services/sync.service';
import './styles.css';

export default function Licenses() {
  const [loading, setLoading] = useState(false);
  const [licenses, setLicenses] = useState([]);
  const [apiKeys, setApiKeys] = useState([]);
  const [selectedApiKey, setSelectedApiKey] = useState(null);
  const [filters, setFilters] = useState({});

  useEffect(() => {
    fetchApiKeys();
  }, []);

  useEffect(() => {
    if (selectedApiKey) {
      fetchLicenses();
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
      console.error('Erro ao carregar chaves API:', error);
      message.error('Erro ao carregar chaves API');
    }
  };

  const fetchLicenses = async () => {
    if (!selectedApiKey) return;

    try {
      setLoading(true);
      const response = await syncService.getMachineInventory(selectedApiKey, 'licenses');
      const licenses = response.result.items || [];
      setLicenses(licenses);
    } catch (error) {
      console.error('Erro ao carregar licenças:', error);
      message.error('Erro ao carregar licenças');
      setLicenses([]);
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = (value) => {
    setFilters(prev => ({ ...prev, license_key: value }));
  };

  const getExpiryStatus = (expiryDate) => {
    if (!expiryDate) return { color: 'default', text: 'N/A' };
    
    // Configura o fuso horário do Brasil
    const timeZone = 'America/Sao_Paulo';
    const now = new Date().toLocaleString('en-US', { timeZone });
    const currentDate = new Date(now);
    
    // Converte a data de expiração para o fuso horário do Brasil
    const expiryInBrazil = new Date(expiryDate).toLocaleString('en-US', { timeZone });
    const expiryDateTime = new Date(expiryInBrazil);
    
    // Calcula a diferença em dias
    const diffTime = expiryDateTime.getTime() - currentDate.getTime();
    const daysUntilExpiry = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (daysUntilExpiry < 0) {
      return { color: 'red', text: 'Expirada' };
    }
    if (daysUntilExpiry < 30) {
      return { color: 'orange', text: `Expira em ${daysUntilExpiry} dias` };
    }
    
    // Formata a data de expiração para exibição
    const formattedDate = expiryDateTime.toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
    
    return { 
      color: 'green', 
      text: `${formattedDate}` 
    };
  };

  const columns = [
    {
      title: 'Chave da Licença',
      dataIndex: 'license_key',
      key: 'license_key',
      ellipsis: true,
      render: (text) => (
        <Tooltip title={text}>
          <span>{text}</span>
        </Tooltip>
      ),
    },
    {
      title: 'Tipo',
      dataIndex: 'is_addon',
      key: 'is_addon',
      render: (isAddon) => (
        <Tag color={isAddon ? 'purple' : 'blue'}>
          {isAddon ? 'Add-on' : 'Principal'}
        </Tag>
      ),
      filters: [
        { text: 'Principal', value: false },
        { text: 'Add-on', value: true },
      ],
      onFilter: (value, record) => record.is_addon === value,
    },
    {
      title: 'Slots Utilizados',
      key: 'slots',
      render: (_, record) => (
        <Tooltip title={`${record.used_slots} de ${record.total_slots} slots utilizados`}>
          <Tag color={record.used_slots >= record.total_slots ? 'red' : 'green'}>
            {record.used_slots}/{record.total_slots}
          </Tag>
        </Tooltip>
      ),
    },
    {
      title: 'Data de Expiração',
      dataIndex: 'expiry_date',
      key: 'expiry_date',
      render: (date) => {
        const status = getExpiryStatus(date);
        return (
          <Tag color={status.color}>
            {status.text}
          </Tag>
        );
      },
      sorter: (a, b) => new Date(a.expiry_date) - new Date(b.expiry_date),
    },
    {
      title: 'Tipo de Subscrição',
      dataIndex: 'subscription_type',
      key: 'subscription_type',
      filters: [
        { text: 'Trial', value: 1 },
        { text: 'Licenciada', value: 2 },
        { text: 'Mensal Herdada', value: 3 },
      ],
      onFilter: (value, record) => record.subscription_type === value,
      render: (type) => {
        const types = {
          1: { text: 'Trial', color: 'orange' },
          2: { text: 'Licenciada', color: 'green' },
          3: { text: 'Mensal Herdada', color: 'blue' },
        };
        return (
          <Tag color={types[type]?.color || 'default'}>
            {types[type]?.text || 'Desconhecido'}
          </Tag>
        );
      },
    },
    {
      title: 'Última Sincronização',
      dataIndex: 'updated_at',
      key: 'updated_at',
      render: (date) => date ? new Date(date).toLocaleString() : 'N/A',
      sorter: (a, b) => new Date(a.created_at) - new Date(b.created_at),
    },
  ];

  return (
    <Card
      title="Licenças"
      extra={
        <Space>
          <Select
            placeholder="Selecione uma chave API"
            value={selectedApiKey}
            onChange={setSelectedApiKey}
            style={{ width: 200 }}
          >
            {apiKeys.map(key => (
              <Select.Option key={key.id} value={key.id}>
                {key.name}
              </Select.Option>
            ))}
          </Select>
          <Input.Search
            placeholder="Buscar por chave"
            allowClear
            onSearch={handleSearch}
            style={{ width: 200 }}
          />
          <Button
            type="primary"
            icon={<SyncOutlined spin={loading} />}
            onClick={fetchLicenses}
            loading={loading}
          >
            Sincronizar
          </Button>
        </Space>
      }
    >
      <Table
        columns={columns}
        dataSource={licenses}
        loading={loading}
        rowKey="id"
        pagination={{
          total: licenses.length,
          pageSize: 10,
          showSizeChanger: true,
          showQuickJumper: true,
          showTotal: (total) => `Total ${total} itens`,
        }}
      />
    </Card>
  );
} 