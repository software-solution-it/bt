import React, { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { 
  ArrowLeftOutlined, CheckCircleOutlined, CloseCircleOutlined,
  LaptopOutlined, SafetyOutlined, ApiOutlined 
} from '@ant-design/icons';
import { motion } from 'framer-motion';
import styles from './index.module.css';

interface Machine {
  endpoint_id: string;
  name: string;
  group_id: string;
  group_name: string;
  is_managed: number;
  is_deleted: number;
  status: string;
  ip_address: string;
  mac_address: string;
  operating_system: string;
  operating_system_version: string;
  label: string;
  last_seen: string;
  machine_type: number;
  company_id: string;
  policy_id: string;
  policy_name: string;
  policy_applied: number;
  malware_status: string;
  agent_info: string;
  state: number;
  modules: string;
  move_state: number;
  managed_with_best: number;
  risk_score: string | null;
  fqdn: string;
  macs: string;
  ssid: string | null;
  created_at: string;
  updated_at: string;
}

const MachineDetails = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const [machine, setMachine] = useState<Machine | null>(location.state?.machineData || null);

  const parsedModules = machine?.modules ? JSON.parse(machine.modules) : {};
  const parsedMalwareStatus = machine?.malware_status ? JSON.parse(machine.malware_status) : {};
  const parsedAgentInfo = machine?.agent_info ? JSON.parse(machine.agent_info) : {};

  return (
    <div className={styles.container}>
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className={styles.fadeIn}
      >
        <div className={styles.breadcrumb}>
          <button 
            onClick={() => navigate('/dashboard')}
            className={styles.backButton}
          >
            <ArrowLeftOutlined /> Voltar para Dashboard
          </button>
        </div>

        {!machine ? (
          <div className={styles.errorAlert}>
            <div className={styles.errorContent}>
              <h3>Erro</h3>
              <p>Dados da máquina não encontrados</p>
              <button onClick={() => navigate('/dashboard')}>
                Voltar ao Dashboard
              </button>
            </div>
          </div>
        ) : (
          <>
            <div className={`${styles.header} ${styles.slideUp}`}>
              <div className={styles.headerContent}>
                <div className={styles.headerInfo}>
                  <h1 className={styles.machineTitle}>{machine.name}</h1>
                  <span className={styles.machineId}>ID: {machine.endpoint_id}</span>
                </div>
                <div className={styles.headerStatus}>
                  <span className={`${styles.statusBadge} ${machine.state === 1 ? styles.statusOnline : styles.statusOffline}`}>
                    {machine.state === 1 ? 'Online' : 'Offline'}
                  </span>
                </div>
              </div>
            </div>

            <div className={styles.grid}>
              <div className={styles.mainContent}>
                <div className={styles.card}>
                  <div className={styles.cardHeader}>
                    <LaptopOutlined className={styles.cardIcon} />
                    <h2>Informações do Sistema</h2>
                  </div>
                  <div className={styles.infoGrid}>
                    <div className={styles.infoItem}>
                      <span className={styles.infoLabel}>FQDN</span>
                      <h3 className={styles.infoValue}>{machine.fqdn || 'N/A'}</h3>
                    </div>
                    <div className={styles.infoItem}>
                      <span className={styles.infoLabel}>Label</span>
                      <h3 className={styles.infoValue}>{machine.label || 'N/A'}</h3>
                    </div>
                    <div className={styles.infoItem}>
                      <span className={styles.infoLabel}>IP</span>
                      <h3 className={styles.infoValue}>{machine.ip_address || 'N/A'}</h3>
                    </div>
                    <div className={styles.infoItem}>
                      <span className={styles.infoLabel}>Sistema Operacional</span>
                      <h3 className={styles.infoValue}>{machine.operating_system || 'N/A'}</h3>
                    </div>
                  </div>
                </div>

                <div className={styles.card}>
                  <div className={styles.cardHeader}>
                    <SafetyOutlined className={styles.cardIcon} />
                    <h2>Módulos de Proteção</h2>
                  </div>
                  <div className={styles.moduleGrid}>
                    {Object.entries(parsedModules).map(([key, value], index) => (
                      <motion.div
                        key={key}
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.1 * index }}
                        className={styles.moduleItem}
                      >
                        {value ? 
                          <CheckCircleOutlined className={`${styles.moduleIcon} ${styles.iconSuccess}`} /> : 
                          <CloseCircleOutlined className={`${styles.moduleIcon} ${styles.iconError}`} />
                        }
                        <span className={styles.moduleName}>{key}</span>
                      </motion.div>
                    ))}
                  </div>
                </div>
              </div>

              <div className={styles.sidebar}>
                <div className={styles.card}>
                  <div className={styles.cardHeader}>
                    <ApiOutlined className={styles.cardIcon} />
                    <h2>Política e Status</h2>
                  </div>
                  <div className={`${styles.policyAlert} ${machine.policy_applied ? styles.policySuccess : styles.policyWarning}`}>
                    <h3 className={styles.policyName}>{machine.policy_name}</h3>
                    <p className={styles.policyStatus}>
                      Status: <strong>{machine.policy_applied ? 'Aplicada' : 'Pendente'}</strong>
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </>
        )}
      </motion.div>
    </div>
  );
};

export default MachineDetails; 