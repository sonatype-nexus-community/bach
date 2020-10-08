<?php
namespace App\IQ;

class IQPolicyResponse
{
    public string $policyAction;
    public string $reportHtmlUrl;
    public bool $isError;
    public array $componentsAffected;
    public array $openPolicyViolations;
    public int $grandfatheredPolicyViolations;

    public function getPolicyActionText(): string {
        switch ($this->policyAction) {
            case 'Failure':
                return 'Put down the wand, time to clean up some policy failures before you compose further!';
            break;
            case 'Warning':
                return 'Your masterpiece is looking good, but you have some warnings to look at. Pause and reflect on these.';
            break;
            case 'None':
                return 'You have composed a masterpiece, no policy actions necessary, compose away!';
            break;
        }
    }

    public function getPolicyActionWarnType(): string {
        switch ($this->policyAction) {
            case 'Failure':
                return 'error';
            break;
            case 'Warning':
                return 'warn';
            break;
            case 'None':
                return 'info';
            break;
        }
    }

    public function getExitCode(): int {
        switch ($this->policyAction) {
            case 'Failure':
                return 1;
            break;
            default:
                return 0;
        }
    }
}
