<?php

declare(strict_types=1);

namespace CihanSenturk\OfxParser;

use SimpleXMLElement;
use CihanSenturk\OfxParser\Entities\AccountInfo;
use CihanSenturk\OfxParser\Entities\BankAccount;
use CihanSenturk\OfxParser\Entities\Institute;
use CihanSenturk\OfxParser\Entities\SignOn;
use CihanSenturk\OfxParser\Entities\Statement;
use CihanSenturk\OfxParser\Entities\Status;
use CihanSenturk\OfxParser\Entities\Transaction;
use CihanSenturk\OfxParser\Exceptions\InvalidDateFormatException;

/**
 * The OFX object
 *
 * Modern, secure, and type-safe OFX/QFX parser for PHP 8.1+
 *
 * @author Cihan Şentürk <cihansenturk96@gmail.com>
 */
class Ofx
{
    /**
     * @var SignOn
     */
    public $signOn;

    /**
     * @var AccountInfo[]
     */
    public $signupAccountInfo;

    /**
     * @var BankAccount[]
     */
    public $bankAccounts = [];

    /**
     * Only populated if there is only one bank account
     * @var BankAccount|null
     * @deprecated This will be removed in future versions
     */
    public $bankAccount;

    /**
     * @param SimpleXMLElement $xml
     * @throws \Exception
     */
    public function __construct(SimpleXMLElement $xml)
    {
        $this->signOn = $this->buildSignOn($xml->SIGNONMSGSRSV1->SONRS);
        $this->signupAccountInfo = $this->buildAccountInfo($xml->SIGNUPMSGSRSV1->ACCTINFOTRNRS);

        if (isset($xml->BANKMSGSRSV1)) {
            $this->bankAccounts = $this->buildBankAccounts($xml);
        } elseif (isset($xml->CREDITCARDMSGSRSV1)) {
            $this->bankAccounts = $this->buildCreditAccounts($xml);
        }

        // Set a helper if only one bank account
        if (count($this->bankAccounts) === 1) {
            $this->bankAccount = $this->bankAccounts[0];
        }
    }

    /**
     * Get the transactions that have been processed
     *
     * @return array
     * @deprecated This will be removed in future versions
     */
    public function getTransactions(): array
    {
        return $this->bankAccount->statement->transactions;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return SignOn
     * @throws \Exception
     */
    private function buildSignOn(SimpleXMLElement $xml): SignOn
    {
        $signOn = new SignOn();

        $signOn->status = $this->buildStatus($xml->STATUS);

        $date = isset($xml->DTSERVER) ? (string) $xml->DTSERVER : null;

        $signOn->date = $this->createDateTimeFromStr($date, true);

        $signOn->language = (string) ($xml->LANGUAGE ?? 'ENG');

        $signOn->institute = new Institute();

        $signOn->institute->name = (string) ($xml->FI->ORG ?? '');
        $signOn->institute->id = (string) ($xml->FI->FID ?? '');

        return $signOn;
    }

    /**
     * @param SimpleXMLElement|null $xml
     * @return array AccountInfo
     */
    private function buildAccountInfo(?SimpleXMLElement $xml = null): array
    {
        if (null === $xml || ! isset($xml->ACCTINFO)) {
            return [];
        }

        $accounts = [];

        foreach ($xml->ACCTINFO as $account) {
            $accountInfo = new AccountInfo();

            $accountInfo->desc = (string) ($account->DESC ?? '');
            $accountInfo->number = (string) ($account->ACCTID ?? '');

            $accounts[] = $accountInfo;
        }

        return $accounts;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return array
     * @throws \Exception
     */
    private function buildCreditAccounts(SimpleXMLElement $xml): array
    {
        // Loop through the bank accounts
        $bankAccounts = [];

        foreach ($xml->CREDITCARDMSGSRSV1->CCSTMTTRNRS as $accountStatement) {
            $bankAccounts[] = $this->buildCreditAccount($accountStatement);
        }

        return $bankAccounts;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return array
     * @throws \Exception
     */
    private function buildBankAccounts(SimpleXMLElement $xml): array
    {
        // Loop through the bank accounts
        $bankAccounts = [];

        foreach ($xml->BANKMSGSRSV1->STMTTRNRS as $accountStatement) {
            foreach ($accountStatement->STMTRS as $statementResponse) {
                $bankAccounts[] = $this->buildBankAccount($accountStatement->TRNUID, $statementResponse);
            }
        }

        return $bankAccounts;
    }

    /**
     * @param string $transactionUid
     * @param SimpleXMLElement $statementResponse
     * @return BankAccount
     * @throws \Exception
     */
    private function buildBankAccount($transactionUid, SimpleXMLElement $statementResponse): BankAccount
    {
        $bankAccount = new BankAccount();

        $bankAccount->transactionUid = $transactionUid;
        $bankAccount->agencyNumber = (string) ($statementResponse->BANKACCTFROM->BRANCHID ?? '');
        $bankAccount->accountNumber = (string) ($statementResponse->BANKACCTFROM->ACCTID ?? '');
        $bankAccount->routingNumber = (string) ($statementResponse->BANKACCTFROM->BANKID ?? '');
        $bankAccount->accountType = (string) ($statementResponse->BANKACCTFROM->ACCTTYPE ?? '');
        $bankAccount->balance = (float) ($statementResponse->LEDGERBAL->BALAMT ?? 0);

        $balance_date = isset($statementResponse->LEDGERBAL->DTASOF) ? (string) $statementResponse->LEDGERBAL->DTASOF : null;

        $bankAccount->balanceDate = $this->createDateTimeFromStr($balance_date, true);

        $bankAccount->statement = new Statement();

        $bankAccount->statement->currency = (string) ($statementResponse->CURDEF ?? 'USD');

        $start_date = isset($statementResponse->BANKTRANLIST->DTSTART) ? (string) $statementResponse->BANKTRANLIST->DTSTART : null;
        $end_date = isset($statementResponse->BANKTRANLIST->DTEND) ? (string) $statementResponse->BANKTRANLIST->DTEND : null;

        $bankAccount->statement->startDate = $this->createDateTimeFromStr($start_date);
        $bankAccount->statement->endDate = $this->createDateTimeFromStr($end_date);

        // Build transactions if they exist
        $transactionsNode = $statementResponse->BANKTRANLIST->STMTTRN ?? null;

        $bankAccount->statement->transactions = $this->buildTransactions($transactionsNode);

        return $bankAccount;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return BankAccount
     * @throws \Exception
     */
    private function buildCreditAccount(SimpleXMLElement $xml): BankAccount
    {
        $nodeName = 'CCACCTFROM';

        if (! isset($xml->CCSTMTRS->$nodeName)) {
            $nodeName = 'BANKACCTFROM';
        }

        $creditAccount = new BankAccount();

        $creditAccount->transactionUid = (string) ($xml->TRNUID ?? '');
        $creditAccount->agencyNumber = (string) ($xml->CCSTMTRS->$nodeName->BRANCHID ?? '');
        $creditAccount->accountNumber = (string) ($xml->CCSTMTRS->$nodeName->ACCTID ?? '');
        $creditAccount->routingNumber = (string) ($xml->CCSTMTRS->$nodeName->BANKID ?? '');
        $creditAccount->accountType = (string) ($xml->CCSTMTRS->$nodeName->ACCTTYPE ?? '');
        $creditAccount->balance = (float) ($xml->CCSTMTRS->LEDGERBAL->BALAMT ?? 0);

        $balance_date = isset($xml->CCSTMTRS->LEDGERBAL->DTASOF) ? (string) $xml->CCSTMTRS->LEDGERBAL->DTASOF : null;

        $creditAccount->balanceDate = $this->createDateTimeFromStr($balance_date, true);

        $creditAccount->statement = new Statement();

        $creditAccount->statement->currency = (string) ($xml->CCSTMTRS->CURDEF ?? 'USD');

        $start_date = isset($xml->CCSTMTRS->BANKTRANLIST->DTSTART) ? (string) $xml->CCSTMTRS->BANKTRANLIST->DTSTART : null;
        $end_date = isset($xml->CCSTMTRS->BANKTRANLIST->DTEND) ? (string) $xml->CCSTMTRS->BANKTRANLIST->DTEND : null;

        $creditAccount->statement->startDate = $this->createDateTimeFromStr($start_date);
        $creditAccount->statement->endDate = $this->createDateTimeFromStr($end_date);

        // Build transactions if they exist
        $transactionsNode = $xml->CCSTMTRS->BANKTRANLIST->STMTTRN ?? null;

        $creditAccount->statement->transactions = $this->buildTransactions($transactionsNode);

        return $creditAccount;
    }

    /**
     * @param SimpleXMLElement|null $transactions
     * @return array
     * @throws \Exception
     */
    private function buildTransactions(?SimpleXMLElement $transactions): array
    {
        $return = [];

        if ($transactions === null) {
            return $return;
        }

        foreach ($transactions as $t) {
            $transaction = new Transaction();

            $transaction->type = (string) ($t->TRNTYPE ?? '');

            $date = isset($t->DTPOSTED) ? (string) ($t->DTPOSTED) : null;

            $transaction->date = $this->createDateTimeFromStr($date);

            if (isset($t->DTUSER) && '' !== (string) ($t->DTUSER)) {
                $transaction->userInitiatedDate = $this->createDateTimeFromStr((string) ($t->DTUSER));
            }

            $amount = isset($t->TRNAMT) ? (string) ($t->TRNAMT) : '0';

            $transaction->amount = $this->createAmountFromStr($amount);
            $transaction->uniqueId = (string) ($t->FITID ?? '');
            $transaction->name = (string) ($t->NAME ?? '');
            $transaction->memo = (string) ($t->MEMO ?? '');
            $transaction->sic = (string) ($t->SIC ?? '');
            $transaction->checkNumber = (string) ($t->CHECKNUM ?? '');

            $return[] = $transaction;
        }

        return $return;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return Status
     */
    private function buildStatus(SimpleXMLElement $xml): Status
    {
        $status = new Status();

        $status->code = (string) ($xml->CODE ?? '');
        $status->severity = (string) ($xml->SEVERITY ?? '');
        $status->message = (string) ($xml->MESSAGE ?? '');

        return $status;
    }

    /**
     * Create a DateTime object from a date string. Supports multiple formats:
     *
     * YYYYMMDDHHMMSS
     * YYYYMMDD
     * DD/MM/YYYY
     * MM/DD/YYYY
     * ISO 8601: YYYY-MM-DD, YYYY-MM-DDTHH:MM:SS, YYYY-MM-DDTHH:MM:SS+00:00
     *
     * @param  string|null $dateString
     * @param  boolean $ignoreErrors
     * @return \DateTime|null $dateString
     * @throws InvalidDateFormatException
     */
    private function createDateTimeFromStr(?string $dateString, bool $ignoreErrors = false): ?\DateTime
    {
        if ((! isset($dateString) || trim($dateString) === '')) return null;

        // Try ISO 8601 format (YYYY-MM-DD, YYYY-MM-DDTHH:MM:SS, with optional timezone)
        // Examples: 2025-02-07, 2025-02-07T14:30:00, 2025-02-07T14:30:00+00:00, 2025-02-07T14:30:00Z
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dateString)) {
            try {
                // DateTime constructor handles ISO 8601 natively
                return new \DateTime($dateString);
            } catch (\Exception $e) {
                if ($ignoreErrors) {
                    return null;
                }
                // Continue to other formats if ISO 8601 parsing fails
            }
        }

        // Try DD/MM/YYYY or MM/DD/YYYY format first (e.g., 02/07/2025)
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateString, $matches)) {
            $part1 = (int) $matches[1];
            $part2 = (int) $matches[2];
            $year = (int) $matches[3];

            // Determine if it's DD/MM/YYYY or MM/DD/YYYY
            // If part1 > 12, it must be day (DD/MM/YYYY format)
            // If part2 > 12, it must be day (MM/DD/YYYY format)
            // Otherwise, assume MM/DD/YYYY (US format) as it's more common in OFX
            if ($part1 > 12) {
                // part1 is day, so DD/MM/YYYY
                $day = $part1;
                $month = $part2;
            } elseif ($part2 > 12) {
                // part2 is day, so MM/DD/YYYY
                $month = $part1;
                $day = $part2;
            } else {
                // Default to MM/DD/YYYY (US format) for OFX files
                $month = $part1;
                $day = $part2;
            }

            $format = $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT) . ' 00:00:00';

            try {
                return new \DateTime($format);
            } catch (\Exception $e) {
                if ($ignoreErrors) {
                    return null;
                }

                throw $e;
            }
        }

        $regex = '/'
            . "(\d{4})(\d{2})(\d{2})?"     // YYYYMMDD             1,2,3
            . "(?:(\d{2})(\d{2})(\d{2}))?" // HHMMSS   - optional  4,5,6
            . "(?:\.(\d{3}))?"             // .XXX     - optional  7
            . "(?:\[(-?\d+)\:(\w{3}\]))?"  // [-n:TZ]  - optional  8,9
            . '/';

        if (preg_match($regex, $dateString, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            $hour = isset($matches[4]) ? $matches[4] : 0;
            $min = isset($matches[5]) ? $matches[5] : 0;
            $sec = isset($matches[6]) ? $matches[6] : 0;

            $format = $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $min . ':' . $sec;

            try {
                return new \DateTime($format);
            } catch (\Exception $e) {
                if ($ignoreErrors) {
                    return null;
                }

                throw $e;
            }
        }

        throw new InvalidDateFormatException($dateString);
    }

    /**
     * Create a formatted number in Float according to different locale options
     *
     * Supports:
     * 000,00 and -000,00
     * 0.000,00 and -0.000,00
     * 0,000.00 and -0,000.00
     * 000.00 and 000.00
     *
     * @param  string $amountString
     * @return float
     */
    private function createAmountFromStr(string $amountString): float
    {
        // Handle simple integers without decimal separators
        if (preg_match('/^[+-]?\d+$/', $amountString)) {
            return (float) $amountString;
        }

        // Decimal mark style (UK/US): 000.00 or 0,000.00
        if (preg_match('/^(-|\+)?([\d,]+)(\.)([\d]{2})$/', $amountString) === 1) {
            return (float)preg_replace(
                ['/([,]+)/', '/\.?([\d]{2})$/'],
                ['', '.$1'],
                $amountString
            );
        }

        // European style: 000,00 or 0.000,00
        if (preg_match('/^(-|\+)?([\d\.]+)(,)([\d]{2})$/', $amountString) === 1) {
            return (float)preg_replace(
                ['/([\.]+)/', '/,?([\d]{2})$/'],
                ['', '.$1'],
                $amountString
            );
        }

        // Fallback: try to convert as-is
        return (float) $amountString;
    }
}
