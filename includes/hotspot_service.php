<?php

if (!function_exists('flexihubFinalizeHotspotMpesaTransaction')) {
    function flexihubFinalizeHotspotMpesaTransaction($gatewayTransactionId)
    {
        global $conn;

        $gatewayTransactionId=(int)$gatewayTransactionId;
        if($gatewayTransactionId<=0) throw new InvalidArgumentException('Invalid M-Pesa gateway transaction.');

        $conn->begin_transaction();
        try{
            $stmt=$conn->prepare("SELECT * FROM payment_gateway_transactions WHERE id=? FOR UPDATE");
            if(!$stmt) throw new RuntimeException('Unable to load M-Pesa transaction.');
            $stmt->bind_param('i',$gatewayTransactionId);
            $stmt->execute();
            $tx=$stmt->get_result()->fetch_assoc();
            $stmt->close();

            if(!$tx) throw new RuntimeException('M-Pesa transaction not found.');
            if(($tx['flow']??'')!=='hotspot') throw new RuntimeException('Transaction is not a hotspot payment.');
            if(($tx['status']??'')==='completed'){
                $conn->commit();
                return $tx;
            }
            if(($tx['status']??'')!=='confirmed') throw new RuntimeException('Hotspot payment is not provider-confirmed.');

            $tenantId=(int)$tx['tenant_id'];
            $saleId=(int)($tx['hotspot_sale_id']??0);
            if($saleId<=0) throw new RuntimeException('Hotspot sale is not linked to the M-Pesa transaction.');

            $stmt=$conn->prepare("
                SELECT s.*, p.name package_name, p.duration_minutes
                FROM hotspot_sales s
                JOIN hotspot_packages p ON p.id=s.package_id AND p.tenant_id=s.tenant_id
                WHERE s.id=? AND s.tenant_id=?
                FOR UPDATE
            ");
            if(!$stmt) throw new RuntimeException('Unable to load hotspot sale.');
            $stmt->bind_param('ii',$saleId,$tenantId);
            $stmt->execute();
            $sale=$stmt->get_result()->fetch_assoc();
            $stmt->close();

            if(!$sale) throw new RuntimeException('Hotspot sale not found.');
            if(in_array(($sale['status']??''),['paid_pending_access','access_active','completed'],true)){
                $stmt=$conn->prepare("UPDATE payment_gateway_transactions SET status='completed',payment_id=NULL WHERE id=? AND tenant_id=?");
                if($stmt){$stmt->bind_param('ii',$gatewayTransactionId,$tenantId);$stmt->execute();$stmt->close();}
                $conn->commit();
                return $tx;
            }

            $duration=max(1,(int)$sale['duration_minutes']);
            $startedAt=date('Y-m-d H:i:s');
            $expiresAt=date('Y-m-d H:i:s',time()+($duration*60));
            $sessionToken=bin2hex(random_bytes(32));

            $stmt=$conn->prepare("
                INSERT INTO hotspot_sessions
                    (tenant_id,sale_id,session_token,mac_address,status,started_at,expires_at)
                VALUES (?,?,?,?,?,?,?)
            ");
            if(!$stmt) throw new RuntimeException('Unable to create hotspot session.');
            $sessionStatus='pending_authorization';
            $mac=$sale['device_mac']??null;
            $stmt->bind_param('iisssss',$tenantId,$saleId,$sessionToken,$mac,$sessionStatus,$startedAt,$expiresAt);
            if(!$stmt->execute()){
                $err=$stmt->error;
                $stmt->close();
                throw new RuntimeException('Unable to create hotspot session: '.$err);
            }
            $sessionId=(int)$conn->insert_id;
            $stmt->close();

            $saleStatus='paid_pending_access';
            $stmt=$conn->prepare("UPDATE hotspot_sales SET status=?,started_at=?,expires_at=? WHERE id=? AND tenant_id=?");
            if(!$stmt) throw new RuntimeException('Unable to update hotspot sale.');
            $stmt->bind_param('sssii',$saleStatus,$startedAt,$expiresAt,$saleId,$tenantId);
            $stmt->execute();
            $stmt->close();

            $completed='completed';
            $stmt=$conn->prepare("UPDATE payment_gateway_transactions SET status=?,confirmed_at=COALESCE(confirmed_at,NOW()) WHERE id=? AND tenant_id=?");
            if(!$stmt) throw new RuntimeException('Unable to complete M-Pesa transaction.');
            $stmt->bind_param('sii',$completed,$gatewayTransactionId,$tenantId);
            $stmt->execute();
            $stmt->close();

            logAudit(
                'hotspot_mpesa_payment_confirmed',
                'hotspot',
                'Hotspot M-Pesa payment confirmed; session queued for network authorization',
                'hotspot_sale',
                $saleId,
                null,
                ['gateway_transaction_id'=>$gatewayTransactionId,'session_id'=>$sessionId,'expires_at'=>$expiresAt]
            );

            $conn->commit();

            $stmt=$conn->prepare("SELECT * FROM payment_gateway_transactions WHERE id=? AND tenant_id=? LIMIT 1");
            if($stmt){
                $stmt->bind_param('ii',$gatewayTransactionId,$tenantId);
                $stmt->execute();
                $fresh=$stmt->get_result()->fetch_assoc();
                $stmt->close();
                return $fresh?:$tx;
            }
            return $tx;
        }catch(Throwable $e){
            $conn->rollback();
            throw $e;
        }
    }
}
