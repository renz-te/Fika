<?php
require __DIR__ . '/../app/bootstrap.php';
// No Auth::requireLogin() because this is a standalone device terminal screen
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Time Clock - Fika</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .numpad-btn {
            @apply w-full h-20 text-3xl font-bold bg-white text-gray-800 border rounded-lg shadow hover:bg-gray-50 active:bg-gray-200 focus:outline-none transition-colors;
        }
    </style>
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center font-sans text-gray-900">

    <!-- Device Setup Modal -->
    <div id="setupModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-8 rounded-lg shadow-xl w-96">
            <h2 class="text-2xl font-bold mb-4">Device Setup</h2>
            <p class="text-sm text-gray-600 mb-4">Configure this device for Time & Attendance.</p>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Branch ID</label>
                <input type="number" id="setupBranchId" class="w-full border rounded p-2">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Device MAC</label>
                <input type="text" id="setupMac" placeholder="00:1A:2B..." class="w-full border rounded p-2">
            </div>
            <button onclick="saveSetup()" class="w-full bg-blue-600 text-white font-bold py-2 rounded hover:bg-blue-700">Save</button>
        </div>
    </div>

    <!-- Main Clock UI -->
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden flex w-full max-w-5xl h-[600px]">
        
        <!-- Left Side: Time and Info -->
        <div class="w-1/2 bg-blue-700 text-white p-12 flex flex-col justify-center">
            <h1 class="text-5xl font-extrabold mb-2 text-blue-100 tracking-tight">Fika Time Clock</h1>
            <div id="liveTime" class="text-7xl font-bold mb-4">00:00</div>
            <div id="liveDate" class="text-2xl text-blue-200 mb-8">Loading date...</div>
            <p class="text-lg text-blue-300">Enter your Employee Code and PIN to securely clock in or out. The system will automatically detect your current shift status.</p>
        </div>

        <!-- Right Side: Input -->
        <div class="w-1/2 p-12 flex flex-col justify-center bg-gray-50 relative">
            <button onclick="openSetup()" class="absolute top-4 right-4 text-gray-300 hover:text-gray-500">⚙️</button>
            
            <div id="messageBox" class="hidden w-full p-4 mb-6 rounded-lg font-bold text-center text-lg"></div>

            <form id="clockForm" onsubmit="handlePunch(event)">
                <div class="mb-6">
                    <label class="block text-gray-600 text-sm font-bold mb-2">Employee Code</label>
                    <input type="text" id="empCode" required autocomplete="off" class="w-full text-3xl p-4 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none text-center tracking-widest font-mono uppercase">
                </div>
                
                <div class="mb-8">
                    <label class="block text-gray-600 text-sm font-bold mb-2">Secure PIN</label>
                    <input type="password" id="empPin" required autocomplete="off" maxlength="6" class="w-full text-3xl p-4 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none text-center tracking-widest font-mono">
                </div>

                <button type="submit" id="submitBtn" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-5 px-4 rounded-xl text-2xl shadow-lg transition-transform transform active:scale-95">
                    CLOCK IN / OUT
                </button>
            </form>
        </div>
    </div>

    <script>
        // Live Clock
        function updateClock() {
            const now = new Date();
            document.getElementById('liveTime').innerText = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            document.getElementById('liveDate').innerText = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Setup & LocalStorage
        const getDeviceData = () => {
            return {
                mac: localStorage.getItem('fika_device_mac'),
                branch: localStorage.getItem('fika_branch_id')
            };
        };

        function openSetup() {
            const data = getDeviceData();
            document.getElementById('setupMac').value = data.mac || '';
            document.getElementById('setupBranchId').value = data.branch || '';
            document.getElementById('setupModal').classList.remove('hidden');
        }

        function saveSetup() {
            localStorage.setItem('fika_device_mac', document.getElementById('setupMac').value);
            localStorage.setItem('fika_branch_id', document.getElementById('setupBranchId').value);
            document.getElementById('setupModal').classList.add('hidden');
        }

        // Initialize setup if missing
        if (!getDeviceData().mac || !getDeviceData().branch) {
            openSetup();
        }

        function showMessage(msg, isError) {
            const box = document.getElementById('messageBox');
            box.innerText = msg;
            box.className = `w-full p-4 mb-6 rounded-lg font-bold text-center text-lg ${isError ? 'bg-red-100 text-red-700 border border-red-300' : 'bg-green-100 text-green-700 border border-green-300'}`;
            box.classList.remove('hidden');
            setTimeout(() => {
                box.classList.add('hidden');
            }, 5000);
        }

        async function handlePunch(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const codeInput = document.getElementById('empCode');
            const pinInput = document.getElementById('empPin');
            
            const device = getDeviceData();
            if (!device.mac || !device.branch) {
                showMessage("Device is not configured.", true);
                openSetup();
                return;
            }

            btn.disabled = true;
            btn.innerText = "Processing...";

            const formData = new FormData();
            formData.append('device_mac', device.mac);
            formData.append('branch_id', device.branch);
            formData.append('employee_code', codeInput.value.toUpperCase());
            formData.append('pin', pinInput.value);

            try {
                const res = await fetch('api/fika_hrms_clock_punch.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                
                if (!res.ok) {
                    showMessage(data.error || "Punch failed", true);
                    pinInput.value = '';
                } else {
                    const action = data.action; // 'IN' or 'OUT'
                    showMessage(`Successfully Clocked ${action}!`, false);
                    codeInput.value = '';
                    pinInput.value = '';
                }
            } catch (err) {
                showMessage("Network Error", true);
            } finally {
                btn.disabled = false;
                btn.innerText = "CLOCK IN / OUT";
                codeInput.focus();
            }
        }
    </script>
</body>
</html>
