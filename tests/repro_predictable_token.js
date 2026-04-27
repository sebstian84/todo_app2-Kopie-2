import axios from 'axios';

const API_URL = 'http://localhost:8000/api/index.php';
const PREDICTED_TOKEN = 'todo_token_secure_frost0xx'; // Predictable: prefix + username

async function reproduce() {
    console.log('🔍 Attempting to exploit predictable token vulnerability...');
    try {
        const response = await axios.get(`${API_URL}/data`, {
            headers: {
                'Authorization': `Bearer ${PREDICTED_TOKEN}`
            }
        });

        if (response.status === 200) {
            console.log('🚨 VULNERABILITY CONFIRMED: Successfully accessed sensitive data using a predicted token without login!');
            process.exit(1);
        } else {
            console.log(`ℹ️ Received status ${response.status}. Vulnerability might not be exploitable as expected.`);
            process.exit(0);
        }
    } catch (error) {
        if (error.response && error.response.status === 401) {
            console.log('✅ Access Denied (401): The predictable token did not work. Vulnerability might already be fixed or environment differs.');
            process.exit(0);
        } else {
            console.error('❌ Error during reproduction:', error.message);
            process.exit(1);
        }
    }
}

reproduce();
