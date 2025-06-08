<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\User;

// Bootstrap Laravel
$app = new Application(realpath(__DIR__));

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

try {
    // Test finding a transaction
    echo "Testing transaction access...\n";
    
    $transaction = Transaction::first();
    if ($transaction) {
        echo "Found transaction: " . $transaction->uuid . "\n";
        echo "Type: " . $transaction->type . "\n";
        echo "Amount: " . $transaction->amount . "\n";
        
        // Test accessing account relationship
        echo "Account: " . ($transaction->account ? $transaction->account->name : 'No account') . "\n";
        
        // Test accessing category relationship  
        echo "Category: " . ($transaction->category ? $transaction->category->name : 'No category') . "\n";
        
        // Check if any issue with relationships
        try {
            $transaction->load(['account', 'category']);
            echo "Relationships loaded successfully\n";
        } catch (Exception $e) {
            echo "Error loading relationships: " . $e->getMessage() . "\n";
        }
        
        // Test the deleteTransaction method
        try {
            $transactionService = app(\App\Services\TransactionService::class);
            echo "TransactionService instantiated successfully\n";
            
            // Test findByUuid method
            $foundTransaction = $transactionService->findByUuid($transaction->uuid);
            if ($foundTransaction) {
                echo "Transaction found by UUID successfully\n";
            } else {
                echo "Transaction NOT found by UUID\n";
            }
            
        } catch (Exception $e) {
            echo "Error with TransactionService: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
        
    } else {
        echo "No transactions found in database\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
