<?php

/*
 * Copyright (C) 2022 Cloud_Etel
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace reach_guard\IPsec\Api;

use reach_guard\Base\ApiControllerBase;
use reach_guard\Core\Backend;
use reach_guard\Core\Config;

/**
 * Class SessionsController
 * @package reach_guard\IPsec\Api
 */
class SessionsController extends ApiControllerBase
{
    private function list_status()
    {
        return json_decode((new Backend())->configdRun('ipsec list status'), true);
    }

    /**
     * Search phase 1 session entries
     * @return array
     */
    public function searchPhase1Action()
    {
        $this->sessionClose();
        $records = [];
        $config = Config::getInstance()->object();
        $data = $this->list_status();
        $phase1s = [];
        if (!empty($config->ipsec->phase1)) {
            foreach ($config->ipsec->phase1 as $p1) {
                if (!empty((string)$p1->ikeid)) {
                    $phase1s[(string)$p1->ikeid] = $p1;
                }
            }
        }
        if (!empty($data)) {
            foreach ($data as $conn => $payload) {
                $record = $payload;
                $record['ikeid'] = substr(explode('-', $conn)[0], 3);
                $record['phase1desc'] = null;
                $record['name'] = $conn;
                if (!empty($phase1s[$record['ikeid']])) {
                    $record['phase1desc'] = (string)$phase1s[$record['ikeid']]->descr;
                }
                $record['connected'] = !empty($record['sas']);
                unset($record['children']);
                unset($record['sas']);
                $records[] = $record;
            }
        }
        return $this->searchRecordsetBase($records);
    }

    /**
     * Search phase 2 session entries
     * @return array
     */
    public function searchPhase2Action()
    {
        $this->sessionClose();
        $records = [];
        $selected_conn = $this->request->getPost('id', 'string', '');
        $config = Config::getInstance()->object();
        $data = $this->list_status();
        $reqids = [];
        if (!empty($config->ipsec->phase2)) {
            foreach ($config->ipsec->phase2 as $p2) {
                if (!empty((string)$p2->reqid)) {
                    $reqids[(string)$p2->reqid] = [
                        "ikeid" => (string)$p2->ikeid,
                        "phase2desc" => (string)$p2->descr
                    ];
                }
            }
        }
        if (!empty($data[$selected_conn]) && !empty($data[$selected_conn]['sas'])) {
            foreach ($data[$selected_conn]['sas'] as $sa) {
                if (!empty($sa['child-sas'])) {
                    foreach ($sa['child-sas'] as $conn => $csa) {
                        $record = $csa;
                        $record['remote-host'] = $sa['remote-host'];
                        if (!empty($reqids[$csa['reqid']])) {
                            $record = array_merge($record, $reqids[$csa['reqid']]);
                        }
                        foreach ($record as $key => $val) {
                            if (is_array($val)) {
                                $record[$key] = implode(' , ', $val);
                            }
                        }
                        $records[] =  $record;
                    }
                }
            }
        }
        return $this->searchRecordsetBase($records);
    }

    /**
     * connect a session
     * @param string $id md 5 hash to identify the spd entry
     * @return array
     */
    public function connectAction($id)
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            (new Backend())-> configdpRun('ipsec connect', [$id]);
            return ["result" => "ok"];
        }
        return ["result" => "failed"];
    }

    /**
     * disconnect a session
     * @param string $id md 5 hash to identify the spd entry
     * @return array
     */
    public function disconnectAction($id)
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            (new Backend())-> configdpRun('ipsec disconnect', [$id]);
            return ["result" => "ok"];
        }
        return ["result" => "failed"];
    }
}
