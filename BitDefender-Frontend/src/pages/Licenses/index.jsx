import { useEffect, useState } from 'react';
import { Table, Card, Input, Space, Tag, Tooltip, message } from 'antd';
import { SearchOutlined } from '@ant-design/icons';
import { syncService } from '../../services/sync.service';
import './styles.css';

export default function Licenses() {
  const [loading, setLoading] = useState(false);
  const [licenses, setLicenses] = useState([]);
  const [filters, setFilters] = useState({});

  useEffect(() => {
    const savedApiKey = sessionStorage.getItem('selectedApiKey');
    if (savedApiKey) {
      fetchLicenses();
    }
  }, []);

  const fetchLicenses = async () => {
    const savedApiKey = sessionStorage.getItem('selectedApiKey');
    if (!savedApiKey) {
      return;
    }

    try {
      setLoading(true);
      const response = await syncService.getMachineInventory(Number(savedApiKey), 'licenses');
      const licenses = response.result.items || [];
      setLicenses(licenses);
    } catch (error) {
      console.error('Erro ao carregar licenças:', error);
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
      title: 'Empresa',
      key: 'company',
      render: (_, record) => (
        <Tooltip title={record.company_name || record.name}>
          <span>{record.company_name || record.name}</span>
        </Tooltip>
      ),
    },
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
      title: 'Tipo de Projeto',
      dataIndex: 'service_type',
      key: 'service_type',
      filters: [
        { text: 'Produtos', value: 'Produtos' },
        { text: 'Serviços', value: 'Serviços' },
      ],
      onFilter: (value, record) => record.service_type === value,
      render: (type) => {
        const types = {
          'Produtos': { text: 'Produtos', color: 'blue' },
          'Serviços': { text: 'Serviços', color: 'green' },
        };
        return (
          <Tag color={types[type]?.color || 'default'}>
            {types[type]?.text || type || 'Desconhecido'}
          </Tag>
        );
      },
    },
    {
      title: 'Última Sincronização',
      dataIndex: 'updated_at',
      key: 'updated_at',
      render: (date) => date ? new Date(date).toLocaleString() : 'N/A',
      sorter: (a, b) => new Date(a.updated_at) - new Date(b.updated_at),
    },
  ];

  return (
    <Card
      title="Licenças"
      extra={
        <Space>
          <Input.Search
            placeholder="Buscar por chave"
            allowClear
            onSearch={handleSearch}
            style={{ width: 200 }}
          />
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