import { useEffect, useState } from 'react';
import { Table, Card, Button, Input, Space, Tag, Tooltip, message } from 'antd';
import { SyncOutlined, SearchOutlined } from '@ant-design/icons';
import { syncService } from '../../services/sync.service';
import './styles.css';

export default function Networks() {
  const [loading, setLoading] = useState(false);
  const [networks, setNetworks] = useState([]);
  const [filters, setFilters] = useState({});

  const fetchNetworks = async () => {
    try {
      setLoading(true);
      const data = await syncService.getFilteredNetworks(filters);
      setNetworks(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching networks:', error);
      setNetworks([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchNetworks();
  }, []);

  const handleSearch = (value) => {
    setFilters(prev => ({ ...prev, name: value }));
  };

  const getStatusColor = (status) => {
    const colors = {
      online: 'green',
      offline: 'red',
      pending: 'orange',
      installing: 'blue',
      installing_failed: 'red',
      uninstalling: 'orange',
      uninstalling_failed: 'red',
      suspended: 'grey',
      default: 'default'
    };
    return colors[status?.toLowerCase()] || colors.default;
  };

  const columns = [
    {
      title: 'Nome',
      dataIndex: 'name',
      key: 'name',
      sorter: (a, b) => a.name.localeCompare(b.name),
    },
    {
      title: 'Grupo',
      dataIndex: 'group_name',
      key: 'group_name',
      filters: [],  // Será preenchido dinamicamente com os grupos disponíveis
      onFilter: (value, record) => record.group_name === value,
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      render: (status) => (
        <Tag color={getStatusColor(status)}>
          {status?.toUpperCase() || 'N/A'}
        </Tag>
      ),
    },
    {
      title: 'IP',
      dataIndex: 'ip_address',
      key: 'ip_address',
    },
    {
      title: 'MAC',
      dataIndex: 'mac_address',
      key: 'mac_address',
    },
    {
      title: 'Sistema Operacional',
      dataIndex: 'operating_system',
      key: 'operating_system',
      filters: [
        { text: 'Windows', value: 'Windows' },
        { text: 'Linux', value: 'Linux' },
        { text: 'macOS', value: 'macOS' },
      ],
      onFilter: (value, record) => record.operating_system?.includes(value),
    },
    {
      title: 'Política',
      dataIndex: 'policy_name',
      key: 'policy_name',
      render: (policy, record) => (
        <Tooltip title={`ID: ${record.policy_id}`}>
          <Tag color={record.policy_applied ? 'green' : 'orange'}>
            {policy || 'Sem política'}
          </Tag>
        </Tooltip>
      ),
    },
    {
      title: 'Último Acesso',
      dataIndex: 'lastSeen',
      key: 'lastSeen',
      render: (date) => date ? new Date(date).toLocaleString() : 'N/A',
      sorter: (a, b) => new Date(a.lastSeen) - new Date(b.lastSeen),
    },
    {
      title: 'FQDN',
      dataIndex: 'fqdn',
      key: 'fqdn',
      ellipsis: true,
    },
  ];

  return (
    <Card
      title="Redes"
      extra={
        <Space>
          <Input.Search
            placeholder="Buscar por nome"
            allowClear
            onSearch={handleSearch}
            style={{ width: 200 }}
          />
          <Button
            type="primary"
            icon={<SyncOutlined spin={loading} />}
            onClick={fetchNetworks}
            loading={loading}
          >
            Sincronizar
          </Button>
        </Space>
      }
    >
      <Table
        columns={columns}
        dataSource={networks}
        loading={loading}
        rowKey="endpoint_id"
        pagination={{
          total: networks.length,
          pageSize: 10,
          showSizeChanger: true,
          showQuickJumper: true,
          showTotal: (total) => `Total ${total} itens`,
        }}
        expandable={{
          expandedRowRender: (record) => (
            <div className="expanded-row">
              <p><strong>Informações do Agente:</strong></p>
              <pre>{JSON.stringify(record.agent_info, null, 2)}</pre>
              
              <p><strong>Status de Malware:</strong></p>
              <pre>{JSON.stringify(record.malware_status, null, 2)}</pre>
              
              <p><strong>Módulos:</strong></p>
              <pre>{JSON.stringify(record.modules, null, 2)}</pre>
            </div>
          ),
        }}
      />
    </Card>
  );
} 