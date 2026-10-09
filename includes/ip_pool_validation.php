<?php
/**
 * Shared IPv4/CIDR validation for tenant IP pools.
 */
if (!function_exists('flexihubIpToLong')) {
    function flexihubIpToLong($ip) {
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) return null;
        $n = ip2long($ip);
        return $n === false ? null : (int)sprintf('%u', $n);
    }
}
if (!function_exists('flexihubParsePoolCidr')) {
    function flexihubParsePoolCidr($value) {
        $value = trim((string)$value);
        if ($value === '') return null;
        if (strpos($value, '/') === false) return null;
        $parts = explode('/', $value, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[1])) return null;
        $ip = flexihubIpToLong($parts[0]); $bits = (int)$parts[1];
        if ($ip === null || $bits < 0 || $bits > 32) return null;
        $mask = $bits === 0 ? 0 : ((0xFFFFFFFF << (32 - $bits)) & 0xFFFFFFFF);
        $network = $ip & $mask; $broadcast = $network | (0xFFFFFFFF ^ $mask);
        return ['bits'=>$bits,'network'=>$network,'broadcast'=>$broadcast,'network_ip'=>long2ip($network),'broadcast_ip'=>long2ip($broadcast),'cidr'=>long2ip($network).'/'.$bits];
    }
}
if (!function_exists('flexihubValidateIpPool')) {
    function flexihubValidateIpPool(mysqli $conn, int $tenantId, array $data, array $columns, int $excludeId = 0): array {
        $errors = [];
        $has = static fn($c) => in_array($c, $columns, true);
        $name = trim((string)($data['name'] ?? ''));
        $networkInput = trim((string)($data['network'] ?? ''));
        $cidrInput = trim((string)($data['cidr'] ?? ''));
        $networkValue = $networkInput !== '' ? $networkInput : $cidrInput;
        $gateway = trim((string)($data['gateway'] ?? ''));
        $start = trim((string)($data['start_ip'] ?? ''));
        $end = trim((string)($data['end_ip'] ?? ''));
        if ($name === '') $errors[] = 'Pool name is required.';
        $cidr = null;
        if ($networkValue === '') {
            $errors[] = 'Network / CIDR is required. Enter a valid IPv4 subnet such as 192.168.10.0/24.';
        } else {
            $cidr = flexihubParsePoolCidr($networkValue);
            if (!$cidr) $errors[] = 'Network must be a valid IPv4 CIDR, for example 192.168.10.0/24.';
            elseif ($cidr['network_ip'] !== explode('/', $networkValue, 2)[0]) $errors[] = 'Use the subnet network address, not a host address, in the CIDR field.';
        }
        $gatewayLong = null;
        if ($gateway !== '') {
            $gatewayLong = flexihubIpToLong($gateway);
            if ($gatewayLong === null) $errors[] = 'Gateway must be a valid IPv4 address.';
            elseif ($cidr && ($gatewayLong < $cidr['network'] || $gatewayLong > $cidr['broadcast'])) $errors[] = 'Gateway must be inside the configured subnet.';
            elseif ($cidr && $cidr['bits'] <= 30 && ($gatewayLong === $cidr['network'] || $gatewayLong === $cidr['broadcast'])) $errors[] = 'Gateway cannot be the network or broadcast address.';
        }
        $startLong = $start === '' ? null : flexihubIpToLong($start);
        $endLong = $end === '' ? null : flexihubIpToLong($end);
        if ($start !== '' && $startLong === null) $errors[] = 'Start IP must be a valid IPv4 address.';
        if ($end !== '' && $endLong === null) $errors[] = 'End IP must be a valid IPv4 address.';
        if (($start === '') !== ($end === '')) $errors[] = 'Enter both Start IP and End IP, or leave both blank.';
        if ($startLong !== null && $endLong !== null && $startLong > $endLong) $errors[] = 'Start IP must not be greater than End IP.';
        if ($cidr && $startLong !== null && $endLong !== null && ($startLong < $cidr['network'] || $endLong > $cidr['broadcast'])) $errors[] = 'The start/end range must fit inside the configured subnet.';
        if ($gatewayLong !== null && $startLong !== null && $endLong !== null && $gatewayLong >= $startLong && $gatewayLong <= $endLong) $errors[] = 'Gateway must not be inside the subscriber allocation range.';
        if ($cidr && $cidr['bits'] <= 30 && $startLong !== null && $endLong !== null && ($startLong <= $cidr['network'] || $endLong >= $cidr['broadcast'])) $errors[] = 'The allocatable range must exclude the subnet network and broadcast addresses.';
        if ($errors) return $errors;
        if (!$has('tenant_id') || $tenantId <= 0) {
            if ($has('tenant_id')) return ['A valid tenant context is required to save an IP pool.'];
        }
        $sql = 'SELECT id'.($has('network')?',network':'').($has('cidr')?',cidr':'').($has('start_ip')?',start_ip':'').($has('end_ip')?',end_ip':'').' FROM ip_pools';
        $where=[];$params=[];$types='';
        if ($has('tenant_id')) { $where[]='tenant_id=?'; $params[]=$tenantId; $types.='i'; }
        if ($excludeId > 0) { $where[]='id<>?'; $params[]=$excludeId; $types.='i'; }
        if ($where) $sql .= ' WHERE '.implode(' AND ',$where);
        $stmt = $conn->prepare($sql);
        if (!$stmt) return ['Unable to validate overlaps against existing IP pools.'];
        if ($params) $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) { $stmt->close(); return ['Unable to validate overlaps against existing IP pools.']; }
        $result = $stmt->get_result();
        $candidateStart = $startLong; $candidateEnd = $endLong;
        if ($candidateStart === null && $cidr) { $candidateStart=$cidr['network']; $candidateEnd=$cidr['broadcast']; }
        while ($existing = $result->fetch_assoc()) {
            $existingCidr = null;
            foreach (['network','cidr'] as $col) if (!empty($existing[$col]) && strpos((string)$existing[$col], '/') !== false) { $existingCidr=flexihubParsePoolCidr($existing[$col]); if ($existingCidr) break; }
            $es = !empty($existing['start_ip']) ? flexihubIpToLong($existing['start_ip']) : null;
            $ee = !empty($existing['end_ip']) ? flexihubIpToLong($existing['end_ip']) : null;
            if ($es === null && $existingCidr) { $es=$existingCidr['network']; $ee=$existingCidr['broadcast']; }
            if ($candidateStart !== null && $candidateEnd !== null && $es !== null && $ee !== null && $candidateStart <= $ee && $es <= $candidateEnd) {
                $errors[] = 'This address range overlaps an existing IP pool (ID '.(int)$existing['id'].'). Choose a non-overlapping range.';
                break;
            }
        }
        $stmt->close();
        return $errors;
    }
}
