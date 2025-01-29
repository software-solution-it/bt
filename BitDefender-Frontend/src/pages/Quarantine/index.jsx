import { useEffect, useState } from 'react';
import { Table, Card, Button, Input, Space, Tag, Tooltip, message } from 'antd';
import { SyncOutlined, SearchOutlined, DeleteOutlined, UndoOutlined } from '@ant-design/icons';
import { syncService } from '../../services/sync.service';
import './styles.css';

export default function Quarantine() {
  const [loading, setLoading] = useState(false);
  const [quarantineItems, setQuarantineItems] = useState([]);
  const [filters, setFilters] = useState({});
  const [selectedItems, setSelectedItems] = useState([]);

  const fetchQuarantineItems = async () => {
    try {
      setLoading(true);
      const data = await syncService.getFilteredQuarantineItems(filters);
      setQuarantineItems(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching quarantine items:', error);
      setQuarantineItems([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchQuarantineItems();
  }, []);

  const handleSearch = (value) => {
    setFilters(prev => ({ ...prev, file_name: value }));
  };

  const handleRestore = async () => {
    if (selectedItems.length === 0) {
      message.warning('Selecione itens para restaurar');
      return;
    }

    try {
      setLoading(true);
      // Implementar lógica de restauração
      fetchQuarantineItems();
    } catch (error) {
      console.error('Error restoring items:', error);
    } finally {
      setLoading(false);
      setSelectedItems([]);
    }
  };

  const handleDelete = async () => {
    if (selectedItems.length === 0) {
      message.warning('Selecione itens para remover');
      return;
    }

    try {
      setLoading(true);
      // Implementar lógica de remoção
      fetchQuarantineItems();
    } catch (error) {
      console.error('Error deleting items:', error);
    } finally {
      setLoading(false);
      setSelectedItems([]);
    }
  };

  const getStatusColor = (status) => {
    const colors = {
      quarantined: 'orange',
      restored: 'green',
      removed: 'red',
      default: 'default'
    };
    return colors[status?.toLowerCase()] || colors.default;
  };

  const columns = [
    {
      title: 'Nome do Arquivo',
      dataIndex: 'file_name',
      key: 'file_name',
      sorter: (a, b) => a.file_name.localeCompare(b.file_name),
    },
    {
      title: 'Caminho',
      dataIndex: 'file_path',
      key: 'file_path',
      ellipsis: true,
      render: (path) => (
        <Tooltip title={path}>
          <span>{path}</span>
        </Tooltip>
      ),
    },
    {
      title: 'Ameaça',
      dataIndex: 'threat_name',
      key: 'threat_name',
    },
    {
      title: 'Tamanho',
      dataIndex: 'file_size',
      key: 'file_size',
      render: (size) => {
        const kb = size / 1024;
        const mb = kb / 1024;
        return mb >= 1 ? `${mb.toFixed(2)} MB` : `${kb.toFixed(2)} KB`;
      },
      sorter: (a, b) => a.file_size - b.file_size,
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
        { text: 'Em Quarentena', value: 'quarantined' },
        { text: 'Restaurado', value: 'restored' },
        { text: 'Removido', value: 'removed' },
      ],
      onFilter: (value, record) => record.status === value,
    },
    {
      title: 'Data de Detecção',
      dataIndex: 'detection_time',
      key: 'detection_time',
      render: (date) => date ? new Date(date).toLocaleString() : 'N/A',
      sorter: (a, b) => new Date(a.detection_time) - new Date(b.detection_time),
    },
  ];

  return (
    <Card
      title="Quarentena"
      extra={
        <Space>
          <Input.Search
            placeholder="Buscar por nome do arquivo"
            allowClear
            onSearch={handleSearch}
            style={{ width: 200 }}
          />
          <Button
            type="primary"
            icon={<SyncOutlined spin={loading} />}
            onClick={fetchQuarantineItems}
            loading={loading}
          >
            Sincronizar
          </Button>
        </Space>
      }
    >
      <div className="table-actions">
        <Space>
          <Button
            type="primary"
            icon={<UndoOutlined />}
            onClick={handleRestore}
            disabled={selectedItems.length === 0}
          >
            Restaurar Selecionados
          </Button>
          <Button
            danger
            icon={<DeleteOutlined />}
            onClick={handleDelete}
            disabled={selectedItems.length === 0}
          >
            Remover Selecionados
          </Button>
        </Space>
      </div>

      <Table
        columns={columns}
        dataSource={quarantineItems}
        loading={loading}
        rowKey="item_id"
        rowSelection={{
          selectedRowKeys: selectedItems,
          onChange: (selectedRowKeys) => setSelectedItems(selectedRowKeys),
        }}
        pagination={{
          total: quarantineItems.length,
          pageSize: 10,
          showSizeChanger: true,
          showQuickJumper: true,
          showTotal: (total) => `Total ${total} itens`,
        }}
      />
    </Card>
  );
} 