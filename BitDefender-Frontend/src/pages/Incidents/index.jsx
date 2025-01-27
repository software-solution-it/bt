import { useEffect, useState } from 'react';
import { Table, Card, Button, Input, Space, Tag, message } from 'antd';
import { SyncOutlined, SearchOutlined } from '@ant-design/icons';
import { syncService } from '../../services/sync.service';
import './styles.css';

export default function Incidents() {
  const [loading, setLoading] = useState(false);
  const [incidents, setIncidents] = useState([]);
  const [filters, setFilters] = useState({});

  const fetchIncidents = async () => {
    try {
      setLoading(true);
      const data = await syncService.getFilteredIncidents(filters);
      setIncidents(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching incidents:', error);
      message.error('Erro ao carregar incidentes');
      setIncidents([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchIncidents();
  }, []);

  const handleSearch = (value) => {
    setFilters(prev => ({ ...prev, hash_value: value }));
  };

  const getStatusColor = (status) => {
    const colors = {
      active: 'green',
      removed: 'red',
      pending: 'orange',
      default: 'default'
    };
    return colors[status?.toLowerCase()] || colors.default;
  };

  const columns = [
    {
      title: 'Tipo de Hash',
      dataIndex: 'hash_type',
      key: 'hash_type',
      filters: [
        { text: 'MD5', value: 'MD5' },
        { text: 'SHA1', value: 'SHA1' },
        { text: 'SHA256', value: 'SHA256' },
      ],
      onFilter: (value, record) => record.hash_type === value,
    },
    {
      title: 'Valor do Hash',
      dataIndex: 'hash_value',
      key: 'hash_value',
    },
    {
      title: 'Fonte',
      dataIndex: 'source_info',
      key: 'source_info',
      ellipsis: true,
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
      filters: [
        { text: 'Ativo', value: 'active' },
        { text: 'Removido', value: 'removed' },
        { text: 'Pendente', value: 'pending' },
      ],
      onFilter: (value, record) => record.status === value,
    },
    {
      title: 'Data de Criação',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (date) => date ? new Date(date).toLocaleString() : 'N/A',
      sorter: (a, b) => new Date(a.created_at) - new Date(b.created_at),
    },
    {
      title: 'Última Atualização',
      dataIndex: 'updated_at',
      key: 'updated_at',
      render: (date) => date ? new Date(date).toLocaleString() : 'N/A',
      sorter: (a, b) => new Date(a.updated_at) - new Date(b.updated_at),
    },
  ];

  return (
    <Card
      title="Incidentes"
      extra={
        <Space>
          <Input.Search
            placeholder="Buscar por hash"
            allowClear
            onSearch={handleSearch}
            style={{ width: 200 }}
          />
          <Button
            type="primary"
            icon={<SyncOutlined spin={loading} />}
            onClick={fetchIncidents}
            loading={loading}
          >
            Sincronizar
          </Button>
        </Space>
      }
    >
      <Table
        columns={columns}
        dataSource={incidents}
        loading={loading}
        rowKey="hash_item_id"
        pagination={{
          total: incidents.length,
          pageSize: 10,
          showSizeChanger: true,
          showQuickJumper: true,
          showTotal: (total) => `Total ${total} itens`,
        }}
      />
    </Card>
  );
} 