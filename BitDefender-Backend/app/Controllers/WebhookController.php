<?php

namespace App\Controllers;

class WebhookController {
    private $logDir;

    public function __construct() {
        $this->logDir = __DIR__ . '/../../logs';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    public function addEvents($request) {
        $body = file_get_contents('php://input');
        
        // Log geral de todos os eventos
        $this->logEvent('webhook.log', $body);

        $data = json_decode($body, true);
        $events = $data['params']['events'] ?? [];

        foreach ($events as $event) {
            switch ($event['module']) {
                case 'av':
                    $this->handleAntimalwareEvent($event);
                    break;
                case 'aph':
                    $this->handleAntiphishingEvent($event);
                    break;
                case 'fw':
                    $this->handleFirewallEvent($event);
                    break;
                case 'avc':
                    $this->handleAdvancedThreatEvent($event);
                    break;
                case 'dp':
                    $this->handleDataProtectionEvent($event);
                    break;
                case 'exchange-malware':
                    $this->handleExchangeMalwareEvent($event);
                    break;
                case 'hd':
                    $this->handleHyperDetectEvent($event);
                    break;
                case 'modules':
                    $this->handleModulesStatusEvent($event);
                    break;
                case 'network-sandboxing':
                    $this->handleSandboxEvent($event);
                    break;
                case 'registration':
                    $this->handleRegistrationEvent($event);
                    break;
                case 'sva':
                    $this->handleSecurityServerEvent($event);
                    break;
                case 'task-status':
                    $this->handleTaskStatusEvent($event);
                    break;
                case 'uc':
                    $this->handleUserControlEvent($event);
                    break;
                default:
                    $this->logEvent('unknown.log', json_encode($event));
                    break;
            }
        }

        return [
            'jsonrpc' => '2.0',
            'result' => 'OK',
            'id' => $data['id'] ?? null
        ];
    }

    private function logEvent($filename, $data) {
        $logEntry = date('Y-m-d H:i:s') . " - " . $data . "\n";
        file_put_contents($this->logDir . '/' . $filename, $logEntry, FILE_APPEND);
    }

    private function handleAntimalwareEvent($event) {
        $logEntry = sprintf(
            "%s - Malware detected: %s, Type: %s, Status: %s on %s (%s)\n",
            date('Y-m-d H:i:s'),
            $event['malware_name'],
            $event['malware_type'],
            $event['final_status'],
            $event['computer_name'],
            $event['computer_ip']
        );
        $this->logEvent('antimalware.log', $logEntry);
    }

    private function handleAntiphishingEvent($event) {
        $logEntry = sprintf(
            "%s - Phishing blocked: %s, Type: %s on %s (%s)\n",
            date('Y-m-d H:i:s'),
            $event['url'],
            $event['aph_type'],
            $event['computer_name'],
            $event['computer_ip']
        );
        $this->logEvent('antiphishing.log', $logEntry);
    }

    private function handleFirewallEvent($event) {
        $logEntry = sprintf(
            "%s - Firewall event: %s from %s on %s (%s)\n",
            date('Y-m-d H:i:s'),
            $event['status'],
            $event['source_ip'] ?? 'unknown',
            $event['computer_name'],
            $event['computer_ip']
        );
        $this->logEvent('firewall.log', $logEntry);
    }

    private function handleAdvancedThreatEvent($event) {
        $logEntry = sprintf(
            "%s - Advanced Threat: %s, Path: %s on %s (%s)\n",
            date('Y-m-d H:i:s'),
            $event['exploit_type'],
            $event['exploit_path'],
            $event['computer_name'],
            $event['computer_ip']
        );
        $this->logEvent('advanced_threat.log', $logEntry);
    }

    private function handleDataProtectionEvent($event) {
        $logEntry = sprintf(
            "%s - Data Protection: %s blocked, Rule: %s on %s (%s)\n",
            date('Y-m-d H:i:s'),
            $event['target_type'],
            $event['blocking_rule_name'],
            $event['computer_name'],
            $event['computer_ip']
        );
        $this->logEvent('data_protection.log', $logEntry);
    }

    private function handleExchangeMalwareEvent($event) {
        $logEntry = sprintf(
            "%s - Exchange Malware: From: %s, To: %s, Subject: %s on %s\n",
            date('Y-m-d H:i:s'),
            $event['sender'],
            implode(', ', $event['recipients']),
            $event['subject'],
            $event['serverName']
        );
        foreach ($event['malware'] as $malware) {
            $logEntry .= sprintf(
                "    - %s (%s): %s - %s\n",
                $malware['malwareName'],
                $malware['malwareType'],
                $malware['actionTaken'],
                $malware['infectedObject']
            );
        }
        $this->logEvent('exchange_malware.log', $logEntry);
    }

    private function handleHyperDetectEvent($event) {
        $logEntry = sprintf(
            "%s - HyperDetect: %s, Type: %s, Status: %s, Attack: %s (%s) on %s\n",
            date('Y-m-d H:i:s'),
            $event['malware_name'],
            $event['malware_type'],
            $event['final_status'],
            $event['attack_type'] ?? 'unknown',
            $event['is_fileless_attack'] ? 'Fileless' : 'File-based',
            $event['computer_name']
        );
        $this->logEvent('hyper_detect.log', $logEntry);
    }

    private function handleModulesStatusEvent($event) {
        $modules = [
            'malware_status' => 'Antimalware',
            'aph_status' => 'Antiphishing',
            'firewall_status' => 'Firewall',
            'avc_status' => 'Advanced Threat Control',
            'ids_status' => 'IDS',
            'uc_web_filtering' => 'Web Filtering',
            'dp_status' => 'Data Protection'
        ];

        $logEntry = sprintf(
            "%s - Module Status Update on %s (%s):\n",
            date('Y-m-d H:i:s'),
            $event['computer_name'],
            $event['computer_ip']
        );

        foreach ($modules as $key => $name) {
            if (isset($event[$key])) {
                $logEntry .= sprintf("    - %s: %s\n", $name, $event[$key] ? 'Enabled' : 'Disabled');
            }
        }
        $this->logEvent('modules_status.log', $logEntry);
    }

    private function handleSandboxEvent($event) {
        $logEntry = sprintf(
            "%s - Sandbox Detection on %s (%s):\n    Type: %s\n",
            date('Y-m-d H:i:s'),
            $event['computerName'],
            $event['computerIp'],
            $event['threatType']
        );

        foreach ($event['filePaths'] as $index => $path) {
            $logEntry .= sprintf(
                "    File %d: %s (Size: %s, Action: %s)\n",
                $index + 1,
                $path,
                $event['fileSizes'][$index] ?? 'unknown',
                $event['remediationActions'][$index] ?? 'none'
            );
        }
        $this->logEvent('sandbox.log', $logEntry);
    }

    private function handleRegistrationEvent($event) {
        $logEntry = sprintf(
            "%s - Registration Status: %s for %s (%s)\n",
            date('Y-m-d H:i:s'),
            $event['product_registration'],
            $event['computer_name'],
            $event['computer_ip']
        );
        $this->logEvent('registration.log', $logEntry);
    }

    private function handleSecurityServerEvent($event) {
        $logEntry = sprintf(
            "%s - Security Server Status on %s (%s):\n" .
            "    Power: %s\n" .
            "    Update Available: %s\n" .
            "    Reboot Required: %s\n" .
            "    Engine Version: %s\n",
            date('Y-m-d H:i:s'),
            $event['computer_name'],
            $event['computer_ip'],
            $event['powered_off'] ? 'OFF' : 'ON',
            $event['product_update_available'] ? 'Yes' : 'No',
            $event['product_reboot_required'] ? 'Yes' : 'No',
            $event['updatesigam'] ?? 'unknown'
        );
        $this->logEvent('security_server.log', $logEntry);
    }

    private function handleTaskStatusEvent($event) {
        $logEntry = sprintf(
            "%s - Task Status: %s\n" .
            "    Computer: %s (%s)\n" .
            "    Task: %s (ID: %s)\n" .
            "    Status: %s\n" .
            "    Success: %s\n" .
            "    Error: %s (Code: %d)\n",
            date('Y-m-d H:i:s'),
            $event['taskName'],
            $event['computer_name'],
            $event['computer_ip'],
            $event['targetName'],
            $event['taskId'],
            $event['status'],
            $event['isSuccessful'] ? 'Yes' : 'No',
            $event['errorMessage'] ?: 'None',
            $event['errorCode']
        );
        $this->logEvent('task_status.log', $logEntry);
    }

    private function handleUserControlEvent($event) {
        $logEntry = sprintf(
            "%s - Content blocked: %s, Type: %s on %s (%s)\n",
            date('Y-m-d H:i:s'),
            $event['url'] ?? $event['application_path'] ?? 'unknown',
            $event['block_type'] ?? 'unknown',
            $event['computer_name'],
            $event['computer_ip']
        );
        $this->logEvent('user_control.log', $logEntry);
    }
} 