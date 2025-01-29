import React from 'react';
import { Table } from 'antd';
import type { TableProps } from 'antd';
import './styles.css';

interface ResponsiveTableProps<T> extends TableProps<T> {
  mobileColumns?: TableProps<T>['columns'];
}

function ResponsiveTable<T extends object>({ 
  columns, 
  mobileColumns,
  ...props 
}: ResponsiveTableProps<T>) {
  const isMobile = window.innerWidth <= 768;
  
  return (
    <div className="responsive-table-wrapper">
      <Table
        {...props}
        columns={isMobile && mobileColumns ? mobileColumns : columns}
        scroll={{ x: 'max-content' }}
        className="responsive-table"
      />
    </div>
  );
}

export default ResponsiveTable; 