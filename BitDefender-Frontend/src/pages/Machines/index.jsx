import { useEffect, useState } from 'react';
import { Table, Card, Button, Input, Space, message } from 'antd';
import { SyncOutlined, SearchOutlined } from '@ant-design/icons';
import { syncService } from '../../services/sync.service';

export default function Machines() {
  const [loading, setLoading] = useState(false);
  const [machines, setMachines] = useState([]);
  const [filters, setFilters] = useState({});

  const fetchMachines = async () => {
    try {
      setLoading(true);
      const data = await syncService.getFilteredMachines(filters);
      setMachines(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching machines:', error);
      message.error('Erro ao carregar máquinas');
      setMachines([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMachines();
  }, []);

  const handleSearch = (value) => {
    setFilters(prev => ({ ...prev, name: value }));
  };

  const handleSync = async () => {
    try {
      setLoading(true);
      await syncService.syncAll();
      message.success('Sincronização concluída com sucesso!');
      fetchMachines();
    } catch (error) {
      console.error('Error syncing data:', error);
      message.error('Erro na sincronização');
    } finally {
      setLoading(false);
    }
  };

  const columns = [
    {
      title: 'Nome',
      dataIndex: 'name',
      key: 'name',
      sorter: (a, b) => a.name.localeCompare(b.name),
    },
    {
      title: 'Tipo',
      dataIndex: 'type',
      key: 'type',
      filters: [
        { text: 'Desktop', value: 'desktop' },
        { text: 'Laptop', value: 'laptop' },
        { text: 'Server', value: 'server' },
      ],
      onFilter: (value, record) => record.type === value,
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      render: (status) => (
        <span className={`status-badge status-${status?.toLowerCase()}`}>
          {status || 'N/A'}
        </span>
      ),
    },
    {
      title: 'Sistema Operacional',
      dataIndex: 'operating_system',
      key: 'operating_system',
    },
    {
      title: 'Último Acesso',
      dataIndex: 'lastSeen',
      key: 'lastSeen',
      render: (date) => date ? new Date(date).toLocaleString() : 'N/A',
      sorter: (a, b) => new Date(a.lastSeen) - new Date(b.lastSeen),
    },
  ];

  return (
    <Card
      title="Máquinas"
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
            onClick={handleSync}
            loading={loading}
          >
            Sincronizar
          </Button>
        </Space>
      }
    >
      <Table
        columns={columns}
        dataSource={machines}
        loading={loading}
        rowKey={(record) => record.machine_id || record.id}
        pagination={{
          total: machines.length,
          pageSize: 10,
          showSizeChanger: true,
          showQuickJumper: true,
          showTotal: (total) => `Total ${total} itens`,
        }}
      />
    </Card>
  );
} 