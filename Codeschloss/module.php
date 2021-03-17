<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

class Codeschloss extends IPSModule
{
    public function Create()
    {
        // Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->CreateProfiles();
        $this->RegisterVariables();
        $this->RegisterTimers();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
        $this->DeleteProfiles();
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        // Never delete this line!
        parent::ApplyChanges();
        // Check kernel runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UnregisterMessages();
        if (!$this->ValidateConfiguration()) {
            return;
        }
        $this->ResetCodeBuffer();
        $this->ResetFailureAttempts();
        $this->RegisterMessages();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('MessageSink', 'SenderID: ' . $SenderID . ', Message: ' . $Message, 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:
                // $Data[0] = actual value
                // $Data[1] = value changed
                // $Data[2] = last value
                // $Data[3] = timestamp actual value
                // $Data[4] = timestamp value changed
                // $Data[5] = timestamp last value
                if ($SenderID == $this->ReadPropertyInteger('DigitZero')) {
                    $digit = 0;
                }
                if ($SenderID == $this->ReadPropertyInteger('DigitOne')) {
                    $digit = 1;
                }
                if ($SenderID == $this->ReadPropertyInteger('DigitTwo')) {
                    $digit = 2;
                }
                if ($SenderID == $this->ReadPropertyInteger('DigitThree')) {
                    $digit = 3;
                }
                if ($SenderID == $this->ReadPropertyInteger('DigitFour')) {
                    $digit = 4;
                }
                if ($SenderID == $this->ReadPropertyInteger('DigitFive')) {
                    $digit = 5;
                }
                if ($SenderID == $this->ReadPropertyInteger('DigitSix')) {
                    $digit = 6;
                }
                if ($SenderID == $this->ReadPropertyInteger('DigitSeven')) {
                    $digit = 7;
                }
                if ($SenderID == $this->ReadPropertyInteger('DigitEight')) {
                    $digit = 8;
                }
                if ($SenderID == $this->ReadPropertyInteger('DigitNine')) {
                    $digit = 9;
                }
                if (isset($digit)) {
                    $this->SendDebug(__FUNCTION__, 'Externes Codeschloss, Ziffer: #' . $digit, 0);
                    $this->CheckExternalCodeInput($digit);
                }
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        return json_encode($formData);
    }

    public function CheckCodeInput(string $Code): bool
    {
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->GetValue('FailureAttempts') >= $this->ReadPropertyInteger('FailureAttempts')) {
            $this->SendDebug(__FUNCTION__, 'Anzahl der Fehlversuche ist erreicht!', 0);
            $this->LogMessage('ID: ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Anzahl der Fehlversuche ist erreicht!', KL_WARNING);
            return false;
        }
        $result = false;
        $value = $this->CheckCode($Code);
        if ($value != 99) {
            $result = true;
        }
        $this->SetValue('LastStatus', $value);
        $this->WriteLogEntry();
        if ($result) {
            $this->ResetFailureAttempts();
        } else {
            $this->SetValue('FailureAttempts', $this->GetValue('FailureAttempts') + 1);
        }
        $this->ResetCodeBuffer();
        return $result;
    }

    public function CheckExternalCodeInput(int $Digit): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $this->SetTimerInterval('ResetCodeBuffer', $this->ReadPropertyInteger('TimeLimit') * 1000);
        $buffer = $this->GetBuffer('TemporaryCode');
        $buffer .= $Digit;
        $this->SetBuffer('TemporaryCode', $buffer);
        $this->SendDebug(__FUNCTION__, 'Buffer: ' . $buffer, 0);
        $digits = strlen($buffer);
        $this->SendDebug(__FUNCTION__, 'Anzahl Ziffern: ' . $digits, 0);
        if ($digits == $this->ReadPropertyInteger('CodeDigits')) {
            $this->SendDebug(__FUNCTION__, 'Code ' . $buffer . ' wird überprüft!', 0);
            $result = false;
            $value = $this->CheckCode($buffer);
            if ($value != 99) {
                $result = true;
            }
            $this->SetValue('LastStatus', $value);
            $this->WriteLogEntry();
            if ($result) {
                $this->SendDebug(__FUNCTION__, 'Code Status: #' . $value, 0);
                $this->ResetFailureAttempts();
            } else {
                $this->SetValue('FailureAttempts', $this->GetValue('FailureAttempts') + 1);
            }
            $this->ResetCodeBuffer();
        }
    }

    public function ResetFailureAttempts(): void
    {
        $this->SetValue('FailureAttempts', 0);
    }

    public function ResetStatus(): void
    {
        $this->SetValue('LastStatus', 0);
    }

    public function ClearLog(): void
    {
        $this->SetValue('Log', '');
    }

    public function ResetCodeBuffer(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt', 0);
        $this->SetBuffer('TemporaryCode', '');
        $this->SendDebug(__FUNCTION__, 'Der Code Buffer wurde gelöscht', 0);
        $this->SetTimerInterval('ResetCodeBuffer', 0);
        $this->SendDebug(__FUNCTION__, 'Der Timer ResetCodeBuffer wurde gestoppt', 0);
    }

    #################### Request action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'CodeInput':
                $this->CheckCodeInput(strval($Value));
                break;

        }
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function RegisterProperties(): void
    {
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyString('CodeStatusOne', '');
        $this->RegisterPropertyString('CodeStatusTwo', '');
        $this->RegisterPropertyString('CodeStatusThree', '');
        $this->RegisterPropertyString('CodeStatusFour', '');
        $this->RegisterPropertyString('CodeStatusFive', '');
        $this->RegisterPropertyInteger('FailureAttempts', 3);
        $this->RegisterPropertyInteger('LogEntries', 5);
        $this->RegisterPropertyInteger('CodeDigits', 4);
        $this->RegisterPropertyInteger('TimeLimit', 5);
        $this->RegisterPropertyInteger('DigitZero', 0);
        $this->RegisterPropertyInteger('DigitOne', 0);
        $this->RegisterPropertyInteger('DigitTwo', 0);
        $this->RegisterPropertyInteger('DigitThree', 0);
        $this->RegisterPropertyInteger('DigitFour', 0);
        $this->RegisterPropertyInteger('DigitFive', 0);
        $this->RegisterPropertyInteger('DigitSix', 0);
        $this->RegisterPropertyInteger('DigitSeven', 0);
        $this->RegisterPropertyInteger('DigitEight', 0);
        $this->RegisterPropertyInteger('DigitNine', 0);
    }

    private function CreateProfiles(): void
    {
        // Last status
        $profileName = 'CS.' . $this->InstanceID . '.LastStatus';
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
        }
        IPS_SetVariableProfileAssociation($profileName, 0, 'Unbekannt', 'Cross', 0);
        IPS_SetVariableProfileAssociation($profileName, 1, 'Status #1', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profileName, 2, 'Status #2', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profileName, 3, 'Status #3', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profileName, 4, 'Status #4', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profileName, 5, 'Status #5', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profileName, 99, 'Falscher Code!', 'Warning', 0xFF0000);
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['LastStatus'];
        foreach ($profiles as $profile) {
            $profileName = 'CS.' . $this->InstanceID . '.' . $profile;
            if (@IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }

    private function RegisterVariables(): void
    {
        // Code input
        $id = @$this->GetIDForIdent('CodeInput');
        $this->RegisterVariableString('CodeInput', 'Code', '', 10);
        $this->EnableAction('CodeInput');
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('CodeInput'), 'Key');
        }
        // Failure Attempts
        $id = @$this->GetIDForIdent('FailureAttempts');
        $this->RegisterVariableInteger('FailureAttempts', 'Fehlversuche', '', 20);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('FailureAttempts'), 'Warning');
        }
        // Last status
        $this->RegisterVariableInteger('LastStatus', 'Letzter Status', 'CS.' . $this->InstanceID . '.LastStatus', 30);
        // Log
        $id = @$this->GetIDForIdent('Log');
        $this->RegisterVariableString('Log', 'Protokoll', '~TextBox', 40);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('Log'), 'Database');
        }
    }

    private function RegisterTimers(): void
    {
        $this->RegisterTimer('ResetCodeBuffer', 0, 'CS_ResetCodeBuffer(' . $this->InstanceID . ');');
    }

    private function UnregisterMessages(): void
    {
        foreach ($this->GetMessageList() as $id => $registeredMessage) {
            foreach ($registeredMessage as $messageType) {
                if ($messageType == VM_UPDATE) {
                    $this->UnregisterMessage($id, VM_UPDATE);
                }
            }
        }
    }

    private function RegisterMessages(): void
    {
        $properties = [
            'DigitZero',
            'DigitOne',
            'DigitTwo',
            'DigitThree',
            'DigitFour',
            'DigitFive',
            'DigitSix',
            'DigitSeven',
            'DigitEight',
            'DigitNine'];
        foreach ($properties as $name) {
            $id = $this->ReadPropertyInteger($name);
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $this->RegisterMessage($id, VM_UPDATE);
            }
        }
    }

    private function ValidateConfiguration(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Validate configuration', 0);
        $status = 102;
        $result = true;
        // Check if codes are different
        $codes = [];
        if (!empty($this->ReadPropertyString('CodeStatusOne'))) {
            array_push($codes, $this->ReadPropertyString('CodeStatusOne'));
        }
        if (!empty($this->ReadPropertyString('CodeStatusTwo'))) {
            array_push($codes, $this->ReadPropertyString('CodeStatusTwo'));
        }
        if (!empty($this->ReadPropertyString('CodeStatusThree'))) {
            array_push($codes, $this->ReadPropertyString('CodeStatusThree'));
        }
        if (!empty($this->ReadPropertyString('CodeStatusFour'))) {
            array_push($codes, $this->ReadPropertyString('CodeStatusFour'));
        }
        if (!empty($this->ReadPropertyString('CodeStatusFive'))) {
            array_push($codes, $this->ReadPropertyString('CodeStatusFive'));
        }
        // We have a duplicate code
        if (count(array_unique($codes)) < count($codes)) {
            $status = 200;
            $result = false;
        }
        // Maintenance mode
        $maintenance = $this->CheckMaintenanceMode();
        if ($maintenance) {
            $status = 104;
            $result = false;
        }
        IPS_SetDisabled($this->InstanceID, $maintenance);
        $this->SetStatus($status);
        return $result;
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = $this->ReadPropertyBoolean('MaintenanceMode');
        if ($result) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        return $result;
    }

    private function CheckCode(string $Code): int
    {
        $value = 99;
        if ($Code == $this->ReadPropertyString('CodeStatusOne')) {
            $value = 1;
        }
        if ($Code == $this->ReadPropertyString('CodeStatusTwo')) {
            $value = 2;
        }
        if ($Code == $this->ReadPropertyString('CodeStatusThree')) {
            $value = 3;
        }
        if ($Code == $this->ReadPropertyString('CodeStatusFour')) {
            $value = 4;
        }
        if ($Code == $this->ReadPropertyString('CodeStatusFive')) {
            $value = 5;
        }
        return $value;
    }

    private function WriteLogEntry(): void
    {
        $date = date('d.m.Y');
        $time = date('H:i:s');
        $string = $date . ', ' . $time . ' - ' . GetValueFormatted($this->GetIDForIdent('LastStatus'));
        $entries = $this->ReadPropertyInteger('LogEntries');
        if ($entries == 1) {
            $this->SetValue('Log', $string);
        }
        if ($entries > 1) {
            // Get existing content first
            $content = array_merge(array_filter(explode("\n", $this->GetValue('Log'))));
            $records = $entries - 1;
            array_splice($content, $records);
            array_unshift($content, $string);
            $newContent = implode("\n", $content);
            $this->SetValue('Log', $newContent);
        }
    }
}