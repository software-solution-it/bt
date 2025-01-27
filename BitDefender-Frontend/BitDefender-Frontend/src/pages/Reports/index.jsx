import { useEffect, useState } from 'react';
import { Table, Card, Button, Input, Space, Tag, Tooltip, message } from 'antd';
import { SyncOutlined, SearchOutlined, DownloadOutlined } from '@ant-design/icons';
import { syncService } from '../../services/sync.service';
import './styles.css';

export default function Reports() {
  const [loading, setLoading] = useState(false);
  const [reports, setReports] = useState([]);
  const [filters, setFilters] = useState({});

  const fetchReports = async () => {
    try {
      setLoading(true);
      const data = await syncService.getFilteredReports(filters);
      setReports(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching reports:', error);
      message.error('Erro ao carregar relatórios');
      setReports([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchReports();
  }, []);

  const handleSearch = (value) => {
    setFilters(prev => ({ ...prev, name: value }));
  };

  const handleDownload = async (report) => {
    try {
      if (!report.download_url) {
        message.warning('URL de download não disponível');
        return;
      }

      // Criar um link temporário para download
      const link = document.createElement('a');
      link.href = report.download_url;
      link.target = '_blank';
      link.download = `${report.name}.${report.format.toLowerCase()}`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } catch (error) {
      console.error('Error downloading report:', error);
      message.error('Erro ao baixar relatório');
    }
  };

  const getStatusColor = (status) => {
    const colors = {
      completed: 'green',
      pending: 'orange',
      failed: 'red',
      expired: 'grey',
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
      title: 'Tipo',
      dataIndex: 'type',
      key: 'type',
      filters: [
        { text: 'Incidentes', value: 'incidents' },
        { text: 'Licenças', value: 'licenses' },
        { text: 'Máquinas', value: 'machines' },
        { text: 'Redes', value: 'networks' },
      ],
      onFilter: (value, record) => record.type === value,
    },
    {
      title: 'Formato',
      dataIndex: 'format',
      key: 'format',
      render: (format) => (
        <Tag color="blue">{format?.toUpperCase()}</Tag>
      ),
      filters: [
        { text: 'PDF', value: 'PDF' },
        { text: 'CSV', value: 'CSV' },
        { text: 'XLSX', value: 'XLSX' },
      ],
      onFilter: (value, record) => record.format === value,
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
      title: 'Tamanho',
      dataIndex: 'file_size',
      key: 'file_size',
      render: (size) => {
        if (!size) return 'N/A';
        const kb = size / 1024;
        const mb = kb / 1024;
        return mb >= 1 ? `${mb.toFixed(2)} MB` : `${kb.toFixed(2)} KB`;
      },
    },
    {
      title: 'Data de Criação',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (date) => date ? new Date(date).toLocaleString() : 'N/A',
      sorter: (a, b) => new Date(a.created_at) - new Date(b.created_at),
    },
    {
      title: 'Expira em',
      dataIndex: 'expires_at',
      key: 'expires_at',
      render: (date) => {
        if (!date) return 'N/A';
        const expiryDate = new Date(date);
        const now = new Date();
        const daysUntilExpiry = Math.ceil((expiryDate - now) / (1000 * 60 * 60 * 24));
        
        return (
          <Tag color={daysUntilExpiry < 0 ? 'red' : daysUntilExpiry < 7 ? 'orange' : 'green'}>
            {daysUntilExpiry < 0 ? 'Expirado' : `${daysUntilExpiry} dias`}
          </Tag>
        );
      },
    },
    {
      title: 'Ações',
      key: 'actions',
      render: (_, record) => (
        <Space>
          <Tooltip title="Download">
            <Button
              type="primary"
              icon={<DownloadOutlined />}
              size="small"
              onClick={() => handleDownload(record)}
              disabled={!record.download_url || record.status !== 'completed'}
            />
          </Tooltip>
        </Space>
      ),
    },
  ];

  return (
    <Card
      title="Relatórios"
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
            onClick={fetchReports}
            loading={loading}
          >
            Sincronizar
          </Button>
        </Space>
      }
    >
      <Table
        columns={columns}
        dataSource={reports}
        loading={loading}
        rowKey="report_id"
        pagination={{
          total: reports.length,
          pageSize: 10,
          showSizeChanger: true,
          showQuickJumper: true,
          showTotal: (total) => `Total ${total} itens`,
        }}
        expandable={{
          expandedRowRender: (record) => (
            <div className="expanded-row">
              {record.parameters && (
                <>
                  <p><strong>Parâmetros do Relatório:</strong></p>
                  <pre>{JSON.stringify(JSON.parse(record.parameters), null, 2)}</pre>
                </>
              )}
            </div>
          ),
        }}
      />
    </Card>
  );
} 