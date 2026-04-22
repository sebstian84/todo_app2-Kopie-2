import axios from 'axios';

const API_URL = 'https://aufgaben.runasp.net/api/index.php';
const TOKEN = 'todo_token_secure_frost0xx'; // Based on users.json and auth logic

async function runTests() {
  console.log('🚀 Starting API Regression Tests...');
  let passed = 0;
  let failed = 0;

  const test = async (name, fn) => {
    try {
      await fn();
      console.log(`✅ PASSED: ${name}`);
      passed++;
    } catch (err) {
      console.error(`❌ FAILED: ${name}`);
      console.error(err.message);
      failed++;
    }
  };

  // Test 1: Fetch Todos
  await test('Fetch Todos (Authorized)', async () => {
    const res = await axios.get(`${API_URL}/data`, {
      headers: { Authorization: `Bearer ${TOKEN}` }
    });
    if (!res.data.todos) throw new Error('Response missing todos array');
  });

  // Test 2: Fetch Time Tracking
  await test('Fetch Time Tracking', async () => {
    const res = await axios.get(`${API_URL}/time`, {
      headers: { Authorization: `Bearer ${TOKEN}` }
    });
    if (typeof res.data !== 'object') throw new Error('Response should be an object');
  });

  // Test 3: Unauthorized Access
  await test('Unauthorized Access Denied', async () => {
    try {
      await axios.get(`${API_URL}/todos`);
      throw new Error('Should have failed with 401');
    } catch (err) {
      if (err.response?.status !== 401) throw err;
    }
  });

  // Test 4: Save Note (Check Encryption/Decryption)
  await test('Notes Persistence', async () => {
    const today = new Date().toISOString().split('T')[0];
    const testContent = `Test content ${Math.random()}`;
    
    // Get current notes
    const resGet = await axios.get(`${API_URL}/notes`, {
      headers: { Authorization: `Bearer ${TOKEN}` }
    });
    const notes = resGet.data || {};
    notes[today] = testContent;

    // Save
    await axios.post(`${API_URL}/notes`, notes, {
      headers: { Authorization: `Bearer ${TOKEN}` }
    });

    // Verify
    const resVerify = await axios.get(`${API_URL}/notes`, {
      headers: { Authorization: `Bearer ${TOKEN}` }
    });
    if (resVerify.data[today] !== testContent) throw new Error('Note content mismatch after save');
  });

  console.log('\n--- Summary ---');
  console.log(`Passed: ${passed}`);
  console.log(`Failed: ${failed}`);
  
  if (failed > 0) process.exit(1);
}

runTests();
