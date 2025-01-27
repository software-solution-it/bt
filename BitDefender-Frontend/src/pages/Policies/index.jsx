import { useEffect, useState } from 'react';
import { Table, Card, Button, Input, Space, Tag, Tooltip, message } from 'antd';
import { SyncOutlined, SearchOutlined } from '@ant-design/icons';
import { syncService } from '../../services/sync.service';
import './styles.css';

export default function Policies() {
  const [loading, setLoading] = useState(false);
  const [policies, setPolicies] = useState([]);
  const [filters, setFilters] = useState({});

  const fetchPolicies = async () => {
    try {
      setLoading(true);
      const data = await syncService.getFilteredPolicies(filters);
      setPolicies(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching policies:', error);
      message.error('Erro ao carregar políticas');
      setPolicies([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPolicies();
  }, []);

  const handleSearch = (value) => {
    setFilters(prev => ({ ...prev, name: value }));
  };

  const getPolicyTypeTag = (type) => {
    const types = {
      1: { color: 'blue', text: 'Padrão' },
      2: { color: 'green', text: 'Personalizada' },
      3: { color: 'purple', text: 'Herdada' },
    };
    return types[type] || { color: 'default', text: 'Desconhecido' };
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
      render: (type) => {
        const typeInfo = getPolicyTypeTag(type);
        return <Tag color={typeInfo.color}>{typeInfo.text}</Tag>;
      },
      filters: [
        { text: 'Padrão', value: 1 },
        { text: 'Personalizada', value: 2 },
        { text: 'Herdada', value: 3 },
      ],
      onFilter: (value, record) => record.type === value,
    },
    {
      title: 'Política Pai',
      dataIndex: 'parent_id',
      key: 'parent_id',
      render: (parentId, record) => (
        parentId ? (
          <Tooltip title={`ID: ${parentId}`}>
            <Tag color="blue">Tem política pai</Tag>
          </Tooltip>
        ) : (
          <Tag>Política raiz</Tag>
        )
      ),
    },
    {
      title: 'Tipo de Herança',
      dataIndex: 'inheritance_type',
      key: 'inheritance_type',
      render: (type) => {
        const types = {
          1: { text: 'Completa', color: 'green' },
          2: { text: 'Parcial', color: 'orange' },
          3: { text: 'Nenhuma', color: 'red' },
        };
        const info = types[type] || { text: 'Desconhecido', color: 'default' };
        return <Tag color={info.color}>{info.text}</Tag>;
      },
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
      title="Políticas"
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
            onClick={fetchPolicies}
            loading={loading}
          >
            Sincronizar
          </Button>
        </Space>
      }
    >
      <Table
        columns={columns}
        dataSource={policies}
        loading={loading}
        rowKey="policy_id"
        pagination={{
          total: policies.length,
          pageSize: 10,
          showSizeChanger: true,
          showQuickJumper: true,
          showTotal: (total) => `Total ${total} itens`,
        }}
        expandable={{
          expandedRowRender: (record) => (
            <div className="expanded-row">
              <p><strong>Descrição:</strong></p>
              <div className="description">{record.description || 'Sem descrição'}</div>
              
              {record.settings && (
                <>
                  <p><strong>Configurações:</strong></p>
                  <div className="settings-container">
                    {Object.entries(JSON.parse(record.settings)).map(([module, settings]) => (
                      <div key={module} className="module-settings">
                        <h4>{module}</h4>
                        <pre>{JSON.stringify(settings, null, 2)}</pre>
                      </div>
                    ))}
                  </div>
                </>
              )}
            </div>
          ),
        }}
      />
    </Card>
  );
} 