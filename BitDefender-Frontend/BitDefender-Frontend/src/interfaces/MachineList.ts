export interface Rights {
  manageCompanies: boolean;
  manageUsers: boolean;
  manageReports: boolean;
  companyManager: boolean;
  manageRemoteShell: boolean;
  manageInventory: boolean;
  managePoliciesRead: boolean;
  managePoliciesWrite: boolean;
  manageMspTrialFeatures?: boolean;
}

export interface Account {
  id: string;
  api_key_id: number;
  email: string;
  full_name: string;
  role: number;
  rights: string; // JSON string of Rights interface
  language: string;
  timezone: string;
  created_at: string;
  updated_at: string;
}

export interface CustomGroup {
  group_id: string;
  api_key_id: number;
  name: string;
  parent_id: string | null;
  created_at: string;
  updated_at: string;
}

export interface License {
  id: number;
  api_key_id: number;
  license_key: string;
  is_addon: number;
  expiry_date: string;
  used_slots: number;
  total_slots: number;
  subscription_type: number;
  own_use: string; // JSON string
  resell: string; // JSON string
  created_at: string;
  updated_at: string;
}

export interface Modules {
  antimalware: boolean;
  firewall: boolean;
  contentControl: boolean;
  powerUser: boolean;
  deviceControl: boolean;
  advancedThreatControl: boolean;
  applicationControl: boolean;
  encryption: boolean;
  networkAttackDefense: boolean;
  antiTampering: boolean;
  advancedAntiExploit: boolean;
  userControl: boolean;
  antiphishing: boolean;
  trafficScan: boolean;
  edrSensor: boolean;
  hyperDetect: boolean;
  remoteEnginesScanning: boolean;
  sandboxAnalyzer: boolean;
  riskManagement: boolean;
}

export interface NetworkInventoryDetails {
  label: string;
  fqdn: string;
  groupId: string;
  isManaged: boolean;
  machineType: number;
  operatingSystemVersion: string;
  ip: string;
  macs: string[];
  ssid: string;
  managedWithBest: boolean;
  policy: {
    id: string;
    name: string;
    applied: boolean;
  };
  modules: Modules;
}

export interface NetworkInventoryItem {
  item_id: string;
  api_key_id: number;
  name: string;
  parent_id: string;
  type: string;
  details: string; // JSON string of NetworkInventoryDetails
  company_id: string;
  lastSeen: string | null;
  is_deleted: number;
  created_at: string;
  updated_at: string;
}

export interface Machine {
  id: number | string;
  machine_id?: string;
  api_key_id: number;
  name: string;
  start_date?: string;
  status?: number;
  fqdn?: string;
  type?: number;
  group_id?: string;
  is_managed?: number;
  operating_system?: string | null;
  operating_system_version?: string;
  ip?: string;
  macs?: string[];
  ssid?: string;
  managed_with_best?: number;
  policy_id?: string;
  policy_name?: string;
  company_id?: string | null;
  state?: number;
  modules?: Modules;
  move_state?: number;
  last_seen?: string | null;
  created_at: string;
  updated_at: string;
}

export interface MachineListResponse {
  jsonrpc: string;
  result: {
    accounts: Account[];
    custom_groups: CustomGroup[];
    endpoints: any[]; // Array vazio no exemplo
    licenses: License[];
    network_inventory: NetworkInventoryItem[];
    machines: Machine[];
  };
  id: null;
} 