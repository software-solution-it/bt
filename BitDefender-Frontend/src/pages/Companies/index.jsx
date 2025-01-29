import { useEffect, useState } from 'react';
import { Table, Card, Button, Input, Space, message } from 'antd';
import { SyncOutlined, SearchOutlined } from '@ant-design/icons';
import { syncService } from '../../services/sync.service';
import './styles.css';

export default function Companies() {
  const [loading, setLoading] = useState(false);
  const [companies, setCompanies] = useState([]);
  const [filters, setFilters] = useState({});

  const fetchCompanies = async () => {
    try {
      setLoading(true);
      const data = await syncService.getFilteredCompanies(filters);
      setCompanies(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching companies:', error);
      setCompanies([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCompanies();
  }, []);

  const handleSearch = (value) => {
    setFilters(prev => ({ ...prev, name: value }));
  };

  const columns = [
    {
      title: 'Nome',
      dataIndex: 'name',
      key: 'name',
      sorter: (a, b) => a.name.localeCompare(b.name),
    },
    {
      title: 'Endereço',
      dataIndex: 'address',
      key: 'address',
    },
    {
      title: 'Telefone',
      dataIndex: 'phone',
      key: 'phone',
    },
    {
      title: 'País',
      dataIndex: 'country',
      key: 'country',
    },
    {
      title: 'Cidade',
      dataIndex: 'city',
      key: 'city',
    },
    {
      title: 'Estado',
      dataIndex: 'state',
      key: 'state',
    },
  ];

  return (
    <Card
      title="Empresas"
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
            onClick={fetchCompanies}
            loading={loading}
          >
            Sincronizar
          </Button>
        </Space>
      }
    >
      <Table
        columns={columns}
        dataSource={companies}
        loading={loading}
        rowKey="id"
        pagination={{
          total: companies.length,
          pageSize: 10,
          showSizeChanger: true,
          showQuickJumper: true,
          showTotal: (total) => `Total ${total} itens`,
        }}
      />
    </Card>
  );
} 