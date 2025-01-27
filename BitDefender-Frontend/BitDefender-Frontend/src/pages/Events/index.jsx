import React, { useState } from 'react';
import { Table, Card, Tag, Space, DatePicker } from 'antd';
import { CheckCircleOutlined, CloseCircleOutlined, SyncOutlined } from '@ant-design/icons';
import './styles.css';

const { RangePicker } = DatePicker;

const Events = () => {
  const [loading, setLoading] = useState(false);

  // Exemplo de dados - substitua por dados reais da API
  const data = [
    {
      id: '1',
      timestamp: '2024-03-20 14:30:00',
      type: 'scan',
      status: 'success',
      description: 'Escaneamento completo finalizado com sucesso',
      machine: 'PC-001'
    },
    {
      id: '2',
      timestamp: '2024-03-20 14:15:00',
      type: 'removal',
      status: 'success',
      description: 'Ameaça removida: Trojan.Gen',
      machine: 'PC-002'
    },
    {
      id: '3',
      timestamp: '2024-03-20 14:00:00',
      type: 'scan',
      status: 'failed',
      description: 'Falha no escaneamento - Tempo limite excedido',
      machine: 'PC-003'
    }
  ];

  const columns = [
    {
      title: 'Data/Hora',
      dataIndex: 'timestamp',
      key: 'timestamp',
      sorter: (a, b) => new Date(a.timestamp) - new Date(b.timestamp)
    },
    {
      title: 'Máquina',
      dataIndex: 'machine',
      key: 'machine',
    },
    {
      title: 'Tipo',
      dataIndex: 'type',
      key: 'type',
      render: (type) => {
        const types = {
          scan: { color: 'blue', label: 'Escaneamento' },
          removal: { color: 'orange', label: 'Remoção' },
          update: { color: 'green', label: 'Atualização' }
        };
        return (
          <Tag color={types[type]?.color}>
            {types[type]?.label || type}
          </Tag>
        );
      },
      filters: [
        { text: 'Escaneamento', value: 'scan' },
        { text: 'Remoção', value: 'removal' },
        { text: 'Atualização', value: 'update' }
      ],
      onFilter: (value, record) => record.type === value
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      render: (status) => {
        const statusConfig = {
          success: { icon: <CheckCircleOutlined />, color: 'success', label: 'Sucesso' },
          failed: { icon: <CloseCircleOutlined />, color: 'error', label: 'Falha' },
          pending: { icon: <SyncOutlined spin />, color: 'processing', label: 'Em andamento' }
        };
        return (
          <Tag icon={statusConfig[status].icon} color={statusConfig[status].color}>
            {statusConfig[status].label}
          </Tag>
        );
      },
      filters: [
        { text: 'Sucesso', value: 'success' },
        { text: 'Falha', value: 'failed' },
        { text: 'Em andamento', value: 'pending' }
      ],
      onFilter: (value, record) => record.status === value
    },
    {
      title: 'Descrição',
      dataIndex: 'description',
      key: 'description',
    }
  ];

  return (
    <Card 
      title="Eventos do Sistema" 
      extra={
        <Space>
          <RangePicker 
            showTime 
            onChange={(dates) => {
              // Implementar filtro por data/hora
              console.log('Datas selecionadas:', dates);
            }} 
          />
        </Space>
      }
    >
      <Table
        columns={columns}
        dataSource={data}
        rowKey="id"
        loading={loading}
        pagination={{
          defaultPageSize: 10,
          showSizeChanger: true,
          showTotal: (total) => `Total de ${total} eventos`
        }}
      />
    </Card>
  );
};

export default Events;
