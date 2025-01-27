import { useEffect, useState } from 'react';
import { Table, Card, Button, Input, Space, Tag, Tooltip, message } from 'antd';
import { SyncOutlined, SearchOutlined } from '@ant-design/icons';
import { syncService } from '../../services/sync.service';
import './styles.css';

export default function Packages() {
  const [loading, setLoading] = useState(false);
  const [packages, setPackages] = useState([]);
  const [filters, setFilters] = useState({});

  const fetchPackages = async () => {
    try {
      setLoading(true);
      const data = await syncService.getFilteredPackages(filters);
      setPackages(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching packages:', error);
      message.error('Erro ao carregar pacotes');
      setPackages([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPackages();
  }, []);

  const handleSearch = (value) => {
    setFilters(prev => ({ ...prev, name: value }));
  };

  const getPackageTypeTag = (type) => {
    const types = {
      0: { color: 'blue', text: 'Padrão' },
      1: { color: 'green', text: 'Personalizado' },
      2: { color: 'purple', text: 'Add-on' },
      3: { color: 'orange', text: 'Beta' }
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
      title: 'Nome do Pacote',
      dataIndex: 'package_name',
      key: 'package_name',
      ellipsis: true,
    },
    {
      title: 'Tipo',
      dataIndex: 'type',
      key: 'type',
      render: (type) => {
        const typeInfo = getPackageTypeTag(type);
        return <Tag color={typeInfo.color}>{typeInfo.text}</Tag>;
      },
      filters: [
        { text: 'Padrão', value: 0 },
        { text: 'Personalizado', value: 1 },
        { text: 'Add-on', value: 2 },
        { text: 'Beta', value: 3 },
      ],
      onFilter: (value, record) => record.type === value,
    },
    {
      title: 'Idioma',
      dataIndex: 'language',
      key: 'language',
      filters: [
        { text: 'Português', value: 'pt-BR' },
        { text: 'Inglês', value: 'en-US' },
        { text: 'Espanhol', value: 'es' },
      ],
      onFilter: (value, record) => record.language === value,
    },
    {
      title: 'Tipo de Produto',
      dataIndex: 'product_type',
      key: 'product_type',
      render: (type) => {
        const types = {
          1: 'Endpoint Security',
          2: 'Advanced Threat Security',
          3: 'Server Security',
        };
        return <Tag>{types[type] || 'Desconhecido'}</Tag>;
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
      title="Pacotes"
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
            onClick={fetchPackages}
            loading={loading}
          >
            Sincronizar
          </Button>
        </Space>
      }
    >
      <Table
        columns={columns}
        dataSource={packages}
        loading={loading}
        rowKey="package_id"
        pagination={{
          total: packages.length,
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
              
              {record.modules && (
                <>
                  <p><strong>Módulos:</strong></p>
                  <div className="modules">
                    {Object.entries(JSON.parse(record.modules)).map(([key, value]) => (
                      <Tag key={key} color={value ? 'green' : 'red'}>
                        {key}: {value ? 'Ativo' : 'Inativo'}
                      </Tag>
                    ))}
                  </div>
                </>
              )}
              
              {record.deployment_options && (
                <>
                  <p><strong>Opções de Implantação:</strong></p>
                  <pre>{JSON.stringify(JSON.parse(record.deployment_options), null, 2)}</pre>
                </>
              )}
            </div>
          ),
        }}
      />
    </Card>
  );
} 