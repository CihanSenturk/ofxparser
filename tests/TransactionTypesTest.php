<?php

declare(strict_types=1);

namespace CihanSenturk\OfxParser\Tests;

use CihanSenturk\OfxParser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Test transaction types and properties
 * Tests for all OFX transaction types and their descriptions
 * 
 * @covers CihanSenturk\OfxParser\Entities\Transaction
 */
class TransactionTypesTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    /**
     * Test all transaction types are correctly parsed
     */
    public function testAllTransactionTypes(): void
    {
        $types = [
            'CREDIT' => 'Generic credit',
            'DEBIT' => 'Generic debit',
            'INT' => 'Interest earned or paid',
            'DIV' => 'Dividend',
            'FEE' => 'FI fee',
            'SRVCHG' => 'Service charge',
            'DEP' => 'Deposit',
            'ATM' => 'ATM debit or credit',
            'POS' => 'Point of sale debit or credit',
            'XFER' => 'Transfer',
            'CHECK' => 'Cheque',
            'PAYMENT' => 'Electronic payment',
            'CASH' => 'Cash withdrawal',
            'DIRECTDEP' => 'Direct deposit',
            'DIRECTDEBIT' => 'Merchant initiated debit',
            'REPEATPMT' => 'Repeating payment/standing order',
            'OTHER' => 'Other',
        ];

        $transactions = '';
        $i = 1;
        foreach ($types as $type => $description) {
            $transactions .= <<<TRANS
<STMTTRN>
<TRNTYPE>{$type}
<DTPOSTED>20231015
<TRNAMT>-{$i}00.00
<FITID>TX{$i}
<NAME>{$description}
</STMTTRN>

TRANS;
            $i++;
        }

        $ofxContent = <<<OFX
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
<SIGNONMSGSRSV1>
<SONRS>
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<DTSERVER>20231101120000
<LANGUAGE>ENG
</SONRS>
</SIGNONMSGSRSV1>
<BANKMSGSRSV1>
<STMTTRNRS>
<TRNUID>1
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<STMTRS>
<CURDEF>USD
<BANKACCTFROM>
<BANKID>123456
<ACCTID>9876543210
<ACCTTYPE>CHECKING
</BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
{$transactions}
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>10000
<DTASOF>20231031
</LEDGERBAL>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($ofxContent);

        $this->assertNotEmpty($ofx->bankAccounts);
        $account = reset($ofx->bankAccounts);

        $this->assertCount(17, $account->statement->transactions);

        // Verify each transaction type
        $i = 0;
        foreach ($types as $type => $expectedDesc) {
            $transaction = $account->statement->transactions[$i];
            $this->assertEquals($type, (string)$transaction->type, "Transaction type mismatch at index {$i}");
            $this->assertEquals($expectedDesc, $transaction->typeDesc(), "Transaction description mismatch for {$type}");
            $i++;
        }
    }

    /**
     * Test transaction with memo field
     */
    public function testTransactionWithMemo(): void
    {
        $ofxContent = <<<OFX
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
<SIGNONMSGSRSV1>
<SONRS>
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<DTSERVER>20231101120000
<LANGUAGE>ENG
</SONRS>
</SIGNONMSGSRSV1>
<BANKMSGSRSV1>
<STMTTRNRS>
<TRNUID>1
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<STMTRS>
<CURDEF>USD
<BANKACCTFROM>
<BANKID>123456
<ACCTID>9876543210
<ACCTTYPE>CHECKING
</BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
<STMTTRN>
<TRNTYPE>PAYMENT
<DTPOSTED>20231015
<TRNAMT>-100.00
<FITID>TX001
<NAME>Electric Company
<MEMO>Monthly electricity bill - October 2023
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>1000
<DTASOF>20231031
</LEDGERBAL>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($ofxContent);

        $this->assertNotEmpty($ofx->bankAccounts);
        $account = reset($ofx->bankAccounts);

        $transaction = reset($account->statement->transactions);

        $this->assertEquals('Electric Company', $transaction->name);
        $this->assertEquals('Monthly electricity bill - October 2023', $transaction->memo);
    }

    /**
     * Test check transaction with check number
     */
    public function testCheckTransaction(): void
    {
        $ofxContent = <<<OFX
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
<SIGNONMSGSRSV1>
<SONRS>
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<DTSERVER>20231101120000
<LANGUAGE>ENG
</SONRS>
</SIGNONMSGSRSV1>
<BANKMSGSRSV1>
<STMTTRNRS>
<TRNUID>1
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<STMTRS>
<CURDEF>USD
<BANKACCTFROM>
<BANKID>123456
<ACCTID>9876543210
<ACCTTYPE>CHECKING
</BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
<STMTTRN>
<TRNTYPE>CHECK
<DTPOSTED>20231015
<TRNAMT>-250.00
<FITID>TX001
<NAME>Check Payment
<CHECKNUM>1025
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>1000
<DTASOF>20231031
</LEDGERBAL>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($ofxContent);

        $this->assertNotEmpty($ofx->bankAccounts);
        $account = reset($ofx->bankAccounts);

        $transaction = reset($account->statement->transactions);

        $this->assertEquals('CHECK', (string)$transaction->type);
        $this->assertEquals('Cheque', $transaction->typeDesc());
        $this->assertEquals('1025', $transaction->checkNumber);
    }

    /**
     * Test transaction with user initiated date
     */
    public function testUserInitiatedDate(): void
    {
        $ofxContent = <<<OFX
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
<SIGNONMSGSRSV1>
<SONRS>
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<DTSERVER>20231101120000
<LANGUAGE>ENG
</SONRS>
</SIGNONMSGSRSV1>
<BANKMSGSRSV1>
<STMTTRNRS>
<TRNUID>1
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<STMTRS>
<CURDEF>USD
<BANKACCTFROM>
<BANKID>123456
<ACCTID>9876543210
<ACCTTYPE>CHECKING
</BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
<STMTTRN>
<TRNTYPE>XFER
<DTPOSTED>20231015
<DTUSER>20231014
<TRNAMT>-500.00
<FITID>TX001
<NAME>Transfer to Savings
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>1000
<DTASOF>20231031
</LEDGERBAL>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($ofxContent);

        $this->assertNotEmpty($ofx->bankAccounts);
        $account = reset($ofx->bankAccounts);

        $transaction = reset($account->statement->transactions);

        $this->assertInstanceOf(\DateTime::class, $transaction->date);
        $this->assertInstanceOf(\DateTime::class, $transaction->userInitiatedDate);

        $this->assertEquals('2023-10-15', $transaction->date->format('Y-m-d'));
        $this->assertEquals('2023-10-14', $transaction->userInitiatedDate->format('Y-m-d'));
    }

    /**
     * Test unique transaction IDs (FITID)
     */
    public function testUniqueTransactionIds(): void
    {
        $ofxContent = <<<OFX
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
<SIGNONMSGSRSV1>
<SONRS>
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<DTSERVER>20231101120000
<LANGUAGE>ENG
</SONRS>
</SIGNONMSGSRSV1>
<BANKMSGSRSV1>
<STMTTRNRS>
<TRNUID>1
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<STMTRS>
<CURDEF>USD
<BANKACCTFROM>
<BANKID>123456
<ACCTID>9876543210
<ACCTTYPE>CHECKING
</BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20231015
<TRNAMT>-100.00
<FITID>2023101500001
<NAME>Transaction 1
</STMTTRN>
<STMTTRN>
<TRNTYPE>CREDIT
<DTPOSTED>20231016
<TRNAMT>200.00
<FITID>2023101600001
<NAME>Transaction 2
</STMTTRN>
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20231017
<TRNAMT>-50.00
<FITID>2023101700001
<NAME>Transaction 3
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>1000
<DTASOF>20231031
</LEDGERBAL>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($ofxContent);

        $this->assertNotEmpty($ofx->bankAccounts);
        $account = reset($ofx->bankAccounts);

        $this->assertCount(3, $account->statement->transactions);

        $ids = [];
        foreach ($account->statement->transactions as $transaction) {
            $this->assertNotEmpty($transaction->uniqueId);
            $ids[] = $transaction->uniqueId;
        }

        // Verify all IDs are unique
        $this->assertCount(3, array_unique($ids), 'All transaction IDs should be unique');
        $this->assertEquals(['2023101500001', '2023101600001', '2023101700001'], $ids);
    }
}
