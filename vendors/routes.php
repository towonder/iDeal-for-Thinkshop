<?php

Router::connect('/ideal/', array('plugin' => 'ideal', 'controller' => 'bank', 'action' => 'index'));
Router::connect('/ideal/sendTransactionRequest', array('plugin' => 'ideal', 'controller' => 'bank', 'action' => 'sendTransactionRequest'));
Router::connect('/ideal/bankConfirm/*', array('plugin' => 'ideal', 'controller' => 'bank', 'action' => 'bankConfirm'));
Router::connect('/ideal/responseError/*', array('plugin' => 'ideal', 'controller' => 'bank', 'action' => 'responseError'));
Router::connect('/ideal/settings', array('plugin' => 'ideal', 'controller' => 'bank', 'action' => 'settings'));
Router::connect('/ideal/generatePrivateKey', array('plugin' => 'ideal', 'controller' => 'bank', 'action' => 'generatePrivateKey'));
Router::connect('/ideal/autoCheck', array('plugin' => 'ideal', 'controller' => 'bank', 'action' => 'autoCheck'));
Router::connect('/ideal/notifySeller/*', array('plugin' => 'ideal', 'controller' => 'bank', 'action' => 'notifySeller'));
Router::connect('/ideal/notifyBuyer/*', array('plugin' => 'ideal', 'controller' => 'bank', 'action' => 'notifyBuyer'));

?>