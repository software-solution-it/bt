import { useEffect, useState } from 'react';
import { Table, Card, Button, Input, Space, Switch, message } from 'antd';
import { SyncOutlined, SearchOutlined } from '@ant-design/icons';
import { syncService } from '../../services/sync.service';
import './styles.css';

export default function Integrations() {
  const [loading, setLoading] = useState(false);
  const [integrations, setIntegrations] = useState([]);
  const [filters, setFilters] = useState({});

  const fetchIntegrations = async () => {
    try {
      setLoading(true);
      const data = await syncService.getFilteredIntegrations(filters);
      setIntegrations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching integrations:', error);
      setIntegrations([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchIntegrations();
  }, []);

  const handleSearch = (value) => {
    setFilters(prev => ({ ...prev, cross_account_role_arn: value }));
  };

  const columns = [
    {
      title: 'Role ARN',
      dataIndex: 'cross_account_role_arn',
      key: 'cross_account_role_arn',
      ellipsis: true,
    },
    {
      title: 'ID Externo',
      dataIndex: 'external_id',
      key: 'external_id',
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      render: (status) => (
        <Switch
          checked={status === 'active'}
          checkedChildren="Ativo"
          unCheckedChildren="Inativo"
          disabled
        />
      ),
    },
    {
      title: 'Última Sincronização',
      dataIndex: 'last_usage_sync',
      key: 'last_usage_sync',
      render: (date) => date ? new Date(date).toLocaleString() : 'N/A',
      sorter: (a, b) => new Date(a.last_usage_sync) - new Date(b.last_usage_sync),
    },
    {
      title: 'Data de Criação',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (date) => date ? new Date(date).toLocaleString() : 'N/A',
      sorter: (a, b) => new Date(a.created_at) - new Date(b.created_at),
    },
  ];

  return (
    <Card
      title="Integrações"
      extra={
        <Space>
          <Input.Search
            placeholder="Buscar por Role ARN"
            allowClear
            onSearch={handleSearch}
            style={{ width: 200 }}
          />
          <Button
            type="primary"
            icon={<SyncOutlined spin={loading} />}
            onClick={fetchIntegrations}
            loading={loading}
          >
            Sincronizar
          </Button>
        </Space>
      }
    >
      <Table
        columns={columns}
        dataSource={integrations}
        loading={loading}
        rowKey="id"
        pagination={{
          total: integrations.length,
          pageSize: 10,
          showSizeChanger: true,
          showQuickJumper: true,
          showTotal: (total) => `Total ${total} itens`,
        }}
      />
    </Card>
  );
} 