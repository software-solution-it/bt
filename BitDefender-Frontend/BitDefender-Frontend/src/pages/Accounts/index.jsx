import { useEffect, useState } from 'react';
import { Table, Card, Button, Input, Space, message, Select } from 'antd';
import { SyncOutlined, SearchOutlined } from '@ant-design/icons';
import { syncService } from '../../services/sync.service';
import './styles.css';

export default function Accounts() {
  const [loading, setLoading] = useState(false);
  const [accounts, setAccounts] = useState([]);
  const [apiKeys, setApiKeys] = useState([]);
  const [selectedApiKey, setSelectedApiKey] = useState(null);
  const [filters, setFilters] = useState({});

  useEffect(() => {
    fetchApiKeys();
  }, []);

  useEffect(() => {
    if (selectedApiKey) {
      fetchAccounts();
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

  const fetchAccounts = async () => {
    if (!selectedApiKey) return;

    try {
      setLoading(true);
      const response = await syncService.getMachineInventory(selectedApiKey, 'accounts');
      const accounts = response.result.items || [];
      setAccounts(accounts);
    } catch (error) {
      console.error('Erro ao carregar contas:', error);
      message.error('Erro ao carregar contas');
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
            placeholder="Buscar por email"
            allowClear
            onSearch={handleSearch}
            style={{ width: 200 }}
          />
          <Button
            type="primary"
            icon={<SyncOutlined spin={loading} />}
            onClick={fetchAccounts}
            loading={loading}
          >
            Sincronizar
          </Button>
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