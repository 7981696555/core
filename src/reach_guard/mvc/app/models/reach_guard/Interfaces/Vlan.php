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

namespace Reach_Guard\Interfaces;

use Phalcon\Messages\Message;
use reach_guard\Base\BaseModel;

class Vlan extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        $all_nodes = $this->getFlatNodes();
        foreach ($all_nodes as $key => $node) {
            if ($validateFullModel || $node->isFieldChanged()) {
                // the item container may have different validations attached.
                $parent = $node->getParentNode();
                // perform plugin specific validations
                switch ($node->getInternalXMLTagName()) {
                    case 'vlanif':
                        $prefix = (strpos((string)$parent->if, 'vlan') === false ? 'vlan' : 'qinq');
                        if ((string)$node == "{$parent->if}_vlan{$parent->tag}") {
                            // legacy device name
                            break;
                        } elseif (!(strpos((string)$node, (string)$prefix) === 0)) {
                            $messages->appendMessage(new Message(
                                sprintf(gettext("Device name does not match type (e.g. %s0XXX)."), (string)$prefix),
                                $key
                            ));
                        } elseif (!preg_match("/^{$prefix}0([0-9\.]){1,16}$/", (string)$node)) {
                            $messages->appendMessage(new Message(
                                sprintf(
                                    gettext(
                                        "A maximum of 16 characters is allowed starting with %s0 combined with ".
                                        "numeric characters and dots[.], e.g. (%s.1.104)"
                                    ),
                                    $prefix,
                                    $prefix
                                ),
                                $key
                            ));
                        }
                        break;
                }
            }
        }
        return $messages;
    }
}
