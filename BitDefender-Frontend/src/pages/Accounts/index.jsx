import { useEffect, useState } from 'react';
import { Table, Card, Input, Space, message } from 'antd';
import { SearchOutlined } from '@ant-design/icons';
import { syncService } from '../../services/sync.service';
import './styles.css';

export default function Accounts() {
  const [loading, setLoading] = useState(false);
  const [accounts, setAccounts] = useState([]);
  const [filters, setFilters] = useState({});

  useEffect(() => {
    const savedApiKey = sessionStorage.getItem('selectedApiKey');
    if (savedApiKey) {
      fetchAccounts();
    }
  }, []);

  const fetchAccounts = async () => {
    const savedApiKey = sessionStorage.getItem('selectedApiKey');
    if (!savedApiKey) {
      return;
    }

    try {
      setLoading(true);
      const response = await syncService.getMachineInventory(Number(savedApiKey), 'accounts');
      const accounts = response.result.items || [];
      setAccounts(accounts);
    } catch (error) {
      console.error('Erro ao carregar contas:', error);
      setAccounts([]);
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = (value) => {
    setFilters(prev => ({ ...prev, email: value }));
  };

  const getRoleName = (role) => {
    const roles = {
      1: 'Administrador da Empresa',
      2: 'Administrador de Rede',
      3: 'Reporter',
      5: 'Customizado',
      default: 'Desconhecido'
    };
    return roles[role] || roles.default;
  };

  const columns = [
    {
      title: 'Email',
      dataIndex: 'email',
      key: 'email',
      sorter: (a, b) => a.email.localeCompare(b.email),
    },
    {
      title: 'Nome Completo',
      dataIndex: 'full_name',
      key: 'full_name',
    },
    {
      title: 'Função',
      dataIndex: 'role',
      key: 'role',
      render: (role) => getRoleName(role),
    },
    {
      title: 'Idioma',
      dataIndex: 'language',
      key: 'language',
    },
    {
      title: 'Fuso Horário',
      dataIndex: 'timezone',
      key: 'timezone',
    },
  ];

  return (
    <Card
      title="Contas"
      extra={
        <Space>
          <Input.Search
            placeholder="Buscar por email"
            allowClear
            onSearch={handleSearch}
            style={{ width: 200 }}
          />
        </Space>
      }
    >
      <Table
        columns={columns}
        dataSource={accounts}
        loading={loading}
        rowKey="id"
        pagination={{
          total: accounts.length,
          pageSize: 10,
          showSizeChanger: true,
          showQuickJumper: true,
          showTotal: (total) => `Total ${total} itens`,
        }}
      />
    </Card>
  );
} 