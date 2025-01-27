import { useEffect, useState } from 'react';
import { Card, Form, Input, Switch, Select, Button, Alert, Space, message } from 'antd';
import { SyncOutlined } from '@ant-design/icons';
import { syncService } from '../../services/sync.service';
import './styles.css';

const { Option } = Select;

export default function PushSettings() {
  const [loading, setLoading] = useState(false);
  const [settings, setSettings] = useState(null);
  const [stats, setStats] = useState(null);
  const [form] = Form.useForm();

  const fetchSettings = async () => {
    try {
      setLoading(true);
      const data = await syncService.getFilteredPushSettings();
      if (Array.isArray(data) && data.length > 0) {
        const currentSettings = data[0];
        setSettings(currentSettings);
        form.setFieldsValue({
          status: currentSettings.status === 1,
          service_type: currentSettings.service_type,
          url: currentSettings.url,
          require_valid_ssl: currentSettings.require_valid_ssl === 1,
          authorization: currentSettings.authorization,
          subscribe_to_events: JSON.parse(currentSettings.subscribe_to_events || '[]'),
        });
      }
    } catch (error) {
      console.error('Error fetching push settings:', error);
      message.error('Erro ao carregar configurações de push');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSettings();
  }, []);

  const handleSubmit = async (values) => {
    try {
      setLoading(true);
      // Transformar os valores para o formato esperado pela API
      const formattedValues = {
        status: values.status ? 1 : 0,
        service_type: values.service_type,
        url: values.url,
        require_valid_ssl: values.require_valid_ssl ? 1 : 0,
        authorization: values.authorization,
        subscribe_to_events: JSON.stringify(values.subscribe_to_events),
      };

      await syncService.syncAll(); // Sincroniza as configurações
      message.success('Configurações salvas com sucesso!');
      fetchSettings(); // Recarrega as configurações
    } catch (error) {
      console.error('Error saving push settings:', error);
      message.error('Erro ao salvar configurações');
    } finally {
      setLoading(false);
    }
  };

  const eventTypes = [
    { value: 'antimalware', label: 'Antimalware' },
    { value: 'antiphishing', label: 'Antiphishing' },
    { value: 'firewall', label: 'Firewall' },
    { value: 'atc_ids', label: 'ATC/IDS' },
    { value: 'data_protection', label: 'Proteção de Dados' },
    { value: 'hyper_detect', label: 'Hyper Detect' },
    { value: 'user_control', label: 'Controle de Usuário' },
    { value: 'product_modules', label: 'Módulos do Produto' },
    { value: 'product_registration', label: 'Registro do Produto' },
    { value: 'security_server_status', label: 'Status do Servidor de Segurança' },
    { value: 'security_server_load', label: 'Carga do Servidor de Segurança' },
    { value: 'exchange_malware', label: 'Exchange Malware' },
    { value: 'sandbox_analyzer', label: 'Sandbox Analyzer' },
    { value: 'task_status', label: 'Status de Tarefas' },
  ];

  return (
    <Card title="Configurações de Push" className="push-settings-card">
      <Form
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{
          status: false,
          require_valid_ssl: true,
          service_type: 'webhook',
          subscribe_to_events: [],
        }}
      >
        <Form.Item
          name="status"
          valuePropName="checked"
          label="Status"
        >
          <Switch
            checkedChildren="Ativo"
            unCheckedChildren="Inativo"
          />
        </Form.Item>

        <Form.Item
          name="service_type"
          label="Tipo de Serviço"
          rules={[{ required: true, message: 'Selecione o tipo de serviço' }]}
        >
          <Select>
            <Option value="webhook">Webhook</Option>
            <Option value="syslog">Syslog</Option>
          </Select>
        </Form.Item>

        <Form.Item
          name="url"
          label="URL"
          rules={[{ required: true, message: 'Informe a URL' }]}
        >
          <Input placeholder="https://seu-webhook.com/endpoint" />
        </Form.Item>

        <Form.Item
          name="require_valid_ssl"
          valuePropName="checked"
          label="Requer SSL Válido"
        >
          <Switch />
        </Form.Item>

        <Form.Item
          name="authorization"
          label="Autorização"
        >
          <Input.Password placeholder="Token de autorização" />
        </Form.Item>

        <Form.Item
          name="subscribe_to_events"
          label="Eventos Inscritos"
          rules={[{ required: true, message: 'Selecione pelo menos um evento' }]}
        >
          <Select
            mode="multiple"
            placeholder="Selecione os eventos"
            style={{ width: '100%' }}
          >
            {eventTypes.map(event => (
              <Option key={event.value} value={event.value}>
                {event.label}
              </Option>
            ))}
          </Select>
        </Form.Item>

        <Form.Item>
          <Space>
            <Button
              type="primary"
              htmlType="submit"
              loading={loading}
              icon={<SyncOutlined spin={loading} />}
            >
              Salvar Configurações
            </Button>
            <Button onClick={fetchSettings} disabled={loading}>
              Recarregar
            </Button>
          </Space>
        </Form.Item>
      </Form>

      {stats && (
        <div className="stats-section">
          <h3>Estatísticas de Push</h3>
          <div className="stats-grid">
            <Alert
              message="Eventos Totais"
              description={stats.events_count}
              type="info"
            />
            <Alert
              message="Eventos de Teste"
              description={stats.test_events_count}
              type="info"
            />
            <Alert
              message="Mensagens Enviadas"
              description={stats.sent_messages_count}
              type="success"
            />
            <Alert
              message="Mensagens com Erro"
              description={stats.error_messages_count}
              type="error"
            />
          </div>
        </div>
      )}
    </Card>
  );
} 