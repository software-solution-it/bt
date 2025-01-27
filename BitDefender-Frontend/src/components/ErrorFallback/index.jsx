import { Button, Result } from 'antd';

export default function ErrorFallback({ error, resetErrorBoundary }) {
  return (
    <Result
      status="error"
      title="Algo deu errado"
      subTitle={error.message}
      extra={[
        <Button type="primary" key="retry" onClick={resetErrorBoundary}>
          Tentar novamente
        </Button>,
      ]}
    />
  );
}