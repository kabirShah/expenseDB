<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-4">
    <div class="max-w-4xl mx-auto bg-white shadow-lg rounded-lg p-6">
        <h2 class="text-2xl font-bold mb-4">Expense Manager</h2>
        
        <!-- Transaction Summary -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="p-4 bg-green-200 rounded-lg text-center">
                <h3 class="text-lg font-semibold">Credits</h3>
                <p class="text-xl font-bold">₹10,000</p>
            </div>
            <div class="p-4 bg-red-200 rounded-lg text-center">
                <h3 class="text-lg font-semibold">Debits</h3>
                <p class="text-xl font-bold">₹5,000</p>
            </div>
            <div class="p-4 bg-blue-200 rounded-lg text-center">
                <h3 class="text-lg font-semibold">Balance</h3>
                <p class="text-xl font-bold">₹5,000</p>
            </div>
        </div>
        
        <!-- Navigation Buttons -->
        <div class="grid grid-cols-2 gap-4">
            <a href="#" class="bg-blue-500 text-white p-3 rounded-lg text-center">View Expenses</a>
            <a href="#" class="bg-green-500 text-white p-3 rounded-lg text-center">Add Expense</a>
            <a href="#" class="bg-purple-500 text-white p-3 rounded-lg text-center">Split Expense</a>
            <a href="#" class="bg-gray-500 text-white p-3 rounded-lg text-center">Scan Receipt</a>
        </div>
        
        <!-- Expense List -->
        <div class="mt-6">
            <h3 class="text-xl font-bold mb-3">Recent Expenses</h3>
            <ul>
                <li class="p-3 bg-gray-50 rounded-lg flex justify-between mb-2">
                    <span>Groceries</span>
                    <span class="text-red-500">-₹1,200</span>
                </li>
                <li class="p-3 bg-gray-50 rounded-lg flex justify-between mb-2">
                    <span>Electricity Bill</span>
                    <span class="text-red-500">-₹800</span>
                </li>
            </ul>
        </div>
    </div>
</body>
</html>