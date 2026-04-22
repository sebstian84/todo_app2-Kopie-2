<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import draggable from 'vuedraggable'
import { Plus, SortAsc, Calendar, Trash2, Tag, X, Clock, Layers, Filter, Archive, HardDrive, User, LogOut, CheckCircle2, ArrowDown, HelpCircle, Menu, Sun, Moon, BarChart3, AlertCircle, CheckCircle, Settings, StickyNote, AlarmClock, Download, ChevronUp, ChevronDown } from 'lucide-vue-next'
import Editor from './components/Editor.vue'
import TodoItem from './components/TodoItem.vue'
import BackupModal from './components/BackupModal.vue'

const API_URL = import.meta.env.DEV ? 'http://localhost:8000/api' : '/api/index.php'
const AUTH_URL = import.meta.env.DEV ? 'http://localhost:8000/api/auth' : '/api/index.php/auth'

const isAuthenticated = ref(!!localStorage.getItem('todo_token'))
const loginData = ref({ username: 'sebastian', password: '' })
const loginError = ref('')

const notes = ref({})
const archivedNotes = ref({})
const todayKey = computed(() => new Date().toISOString().split('T')[0])
const isSavingNotes = ref(false)
const expandedPastNoteKeys = ref([]) // IDs of expanded past notes

const workLogs = ref({}) // Date -> { accumulatedMs, isRunning, startTimeStamp, pause, start, end }
const isSavingTime = ref(false)
const elapsedTime = ref(0) // CURRENT session ms
let timerInterval = null

const appLog = (action, data = {}) => {
  const timestamp = new Date().toLocaleTimeString('de-DE')
  console.log(`[Timer ${timestamp}] ${action}`, JSON.parse(JSON.stringify(data)))
}

const timeFilter = ref({
  from: new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0],
  to: new Date().toISOString().split('T')[0]
})

const userSettings = ref({
  rowHeight: 2.5, // rem
  fontSize: 0.9,  // rem
  compactMode: false,
  defaultPause: 30, // minutes
  weeklyGoal: 40    // hours
})

// Axios config
axios.defaults.withCredentials = true
axios.interceptors.request.use(config => {
  const token = localStorage.getItem('todo_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

const applyVisualSettings = () => {
  document.documentElement.style.setProperty('--todo-row-height', `${userSettings.value.rowHeight}rem`)
  document.documentElement.style.setProperty('--todo-font-size', `${userSettings.value.fontSize}rem`)
}

watch(userSettings, (newSettings) => {
  localStorage.setItem('flattask_settings', JSON.stringify(newSettings))
  applyVisualSettings()
}, { deep: true })

axios.interceptors.response.use(
  res => res,
  err => {
    if (err.response?.status === 401) {
      localStorage.removeItem('todo_token')
      isAuthenticated.value = false
    }
    return Promise.reject(err)
  }
)

const handleLogin = async () => {
  try {
    const res = await axios.post(`${AUTH_URL}/login`, loginData.value)
    localStorage.setItem('todo_token', res.data.token)
    isAuthenticated.value = true
    fetchData()
  } catch (err) {
    loginError.value = 'Login fehlgeschlagen. Bitte überprüfen Sie Ihre Daten.'
  }
}

const handleLogout = async () => {
  try { await axios.post(`${AUTH_URL}/logout`) } catch (err) {}
  localStorage.removeItem('todo_token')
  isAuthenticated.value = false
}

const todos = ref([])
const archivedTodos = ref([])
const isLoaded = ref(false)
const currentView = ref('main')
const showBackupModal = ref(false)

// Settings
const activeTags = ref([])
const isExclusive = ref(false)
const aggregation = ref('none')
const groupByTags = ref(false)
const activeSort = ref({ by: 'order', dir: 'asc' })
const showCompleted = ref(false)
const isDarkMode = ref(localStorage.getItem('darkMode') === 'true')

// Initial load of settings
const savedSettings = localStorage.getItem('flattask_settings')
if (savedSettings) {
  try {
    userSettings.value = { ...userSettings.value, ...JSON.parse(savedSettings) }
  } catch (e) { console.error("Error parsing settings", e) }
}

const newTodo = ref({ name: '', description: '', targetDate: '', tags: [], status: 'offen' })
const newTodoTagInput = ref('')
const showNewForm = ref(false)
const searchQuery = ref('')
const searchExpanded = ref(false)
const searchInputRef = ref(null)
const mobileMenuOpen = ref(false)

const toggleSearch = () => {
  searchExpanded.value = !searchExpanded.value
  if (searchExpanded.value) {
    setTimeout(() => searchInputRef.value?.focus(), 100)
  }
}

const profileData = ref({ username: 'frost0xx', password: '' })
const profileStatus = ref('')
const showUserMenu = ref(false)

const fetchData = async () => {
  try {
    const [dataRes, settingsRes, archiveRes] = await Promise.all([
      axios.get(`${API_URL}/data`),
      axios.get(`${API_URL}/settings`),
      axios.get(`${API_URL}/archive`).catch(() => ({ data: { archivedTodos: [] } }))
    ])
    
    // Todos
    todos.value = (dataRes.data.todos || []).map(t => ({
      ...t,
      tags: t.tags || (t.category ? [t.category] : [])
    }))

    archivedTodos.value = archiveRes.data.archivedTodos || []

    // Settings
    const s = settingsRes.data
    if (s.activeTags) activeTags.value = s.activeTags
    if (s.isExclusive !== undefined) isExclusive.value = s.isExclusive
    if (s.aggregation) aggregation.value = s.aggregation
    if (s.groupByTags !== undefined) groupByTags.value = s.groupByTags
    if (s.activeSort) activeSort.value = s.activeSort
    if (s.showCompleted !== undefined) showCompleted.value = s.showCompleted

    isLoaded.value = true
  } catch (err) { console.error("Error fetching data", err) }
}

const updateCredentials = async () => {
  if (!profileData.value.username || !profileData.value.password) {
    profileStatus.value = 'Bitte beide Felder ausfüllen.'
    return
  }
  try {
    await axios.post(`${AUTH_URL}/update`, profileData.value)
    profileStatus.value = 'Erfolgreich gespeichert!'
    setTimeout(() => { profileStatus.value = ''; currentView.value = 'main' }, 1500)
  } catch (err) {
    profileStatus.value = 'Fehler beim Speichern.'
  }
}

const handleRevived = () => {
  fetchData()
}

const syncTodos = async () => {
  try { await axios.post(`${API_URL}/todos`, { todos: todos.value }) } 
  catch (err) { console.error("Error syncing todos", err) }
}

const syncArchive = async () => {
  try { await axios.post(`${API_URL}/archive`, { archivedTodos: archivedTodos.value }) } 
  catch (err) { console.error("Error syncing archive", err) }
}

const syncSettings = async () => {
  if (!isLoaded.value) return
  try {
    await axios.post(`${API_URL}/settings`, {
      activeTags: activeTags.value,
      isExclusive: isExclusive.value,
      aggregation: aggregation.value,
      groupByTags: groupByTags.value,
      activeSort: activeSort.value,
      showCompleted: showCompleted.value
    })
  } catch (err) { console.error("Error syncing settings", err) }
}

// Watch for setting changes
watch([activeTags, isExclusive, aggregation, groupByTags, activeSort, showCompleted], syncSettings, { deep: true })

const addTodo = () => {
  if (!newTodo.value.name) return
  const id = Date.now().toString()
  const order = todos.value.length > 0 ? Math.max(...todos.value.map(t => t.order)) + 1 : 0
  if (newTodoTagInput.value) {
    newTodo.value.tags = newTodoTagInput.value.split(',').map(s => s.trim()).filter(Boolean)
  }
  todos.value.push({ ...newTodo.value, id, order })
  newTodo.value = { name: '', description: '', targetDate: '', tags: [], status: 'offen' }
  newTodoTagInput.value = ''
  showNewForm.value = false
  syncTodos()
}

const deleteTodo = (id) => {
  const todoToArchive = todos.value.find(t => t.id === id)
  if (todoToArchive) {
    archivedTodos.value.push(todoToArchive)
    todos.value = todos.value.filter(t => t.id !== id)
    syncTodos()
    syncArchive()
  }
}

const reviveTodo = (id) => {
  const todoToRevive = archivedTodos.value.find(t => t.id === id)
  if (todoToRevive) {
    todos.value.push(todoToRevive)
    archivedTodos.value = archivedTodos.value.filter(t => t.id !== id)
    syncTodos()
    syncArchive()
  }
}

const updateTodo = (id, updates) => {
  const index = todos.value.findIndex(t => t.id === id)
  if (index !== -1) {
    todos.value[index] = { ...todos.value[index], ...updates }
    syncTodos()
  }
}

const onDragEnd = () => {
  if (aggregation.value !== 'none' || groupByTags.value || activeTags.value.length > 0 || activeSort.value.by !== 'order') return
  todos.value.forEach((todo, index) => { todo.order = index })
  syncTodos()
}

const getWeekNumber = (date) => {
  const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
  const dayNum = d.getUTCDay() || 7
  d.setUTCDate(d.getUTCDate() + 4 - dayNum)
  const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1))
  return Math.ceil((((d - yearStart) / 86400000) + 1) / 7)
}

const getWeekRange = (date) => {
  const d = new Date(date)
  const day = d.getDay()
  const diff = d.getDate() - day + (day === 0 ? -6 : 1) // Monday
  const monday = new Date(d.setDate(diff))
  const sunday = new Date(monday)
  sunday.setDate(monday.getDate() + 6)
  
  const fmt = (d) => d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
  return `${fmt(monday)} - ${fmt(sunday)}`
}

const getGroupKey = (todo) => {
  if (!todo.targetDate) return 'Kein Datum'
  const date = new Date(todo.targetDate)
  if (isNaN(date.getTime())) return 'Kein Datum'
  if (aggregation.value === 'daily') return todo.targetDate
  if (aggregation.value === 'weekly') return `KW ${getWeekNumber(date)} (${date.getFullYear()})`
  if (aggregation.value === 'monthly') return date.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' })
  return 'Alle'
}

const getGroupLabel = (key) => {
  if (key === 'Kein Datum' || key === 'Alle') return key
  if (aggregation.value === 'daily') {
    const d = new Date(key)
    return d.toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' })
  }
  if (aggregation.value === 'weekly') {
    const match = key.match(/KW (\d+) \((\d+)\)/)
    if (match) {
      const d = getDateFromWeekString(key)
      if (d) return `${key} [${getWeekRange(new Date(d))}]`
    }
  }
  return key
}

const filteredTodos = computed(() => {
  let result = [...todos.value]
  if (!showCompleted.value) {
    result = result.filter(t => t.status !== 'erledigt')
  }
  if (activeTags.value.length > 0) {
    if (isExclusive.value) result = result.filter(t => activeTags.value.every(tag => t.tags && t.tags.includes(tag)))
    else result = result.filter(t => activeTags.value.some(tag => t.tags && t.tags.includes(tag)))
  }
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(t => 
      (t.name && t.name.toLowerCase().includes(q)) || 
      (t.description && t.description.toLowerCase().includes(q))
    )
  }
  return result
})

const groupedTodos = computed(() => {
  if (aggregation.value === 'none' && !groupByTags.value) return null
  let timeGroups = {}
  filteredTodos.value.forEach(todo => {
    const key = getGroupKey(todo)
    if (!timeGroups[key]) timeGroups[key] = []
    timeGroups[key].push(todo)
  })

  // Ensure current period is always shown if aggregation is active
  if (aggregation.value !== 'none') {
    const now = new Date()
    let currentKey = ''
    if (aggregation.value === 'daily') {
      const y = now.getFullYear()
      const m = String(now.getMonth() + 1).padStart(2, '0')
      const d = String(now.getDate()).padStart(2, '0')
      currentKey = `${y}-${m}-${d}`
    } else if (aggregation.value === 'weekly') {
      currentKey = `KW ${getWeekNumber(now)} (${now.getFullYear()})`
    } else if (aggregation.value === 'monthly') {
      currentKey = now.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' })
    }
    if (currentKey && !timeGroups[currentKey]) timeGroups[currentKey] = []
  }
  const finalGroups = []
  const sortedTimeKeys = Object.keys(timeGroups).sort((a, b) => {
    if (a === 'Kein Datum') return 1; if (b === 'Kein Datum') return -1; if (a === 'Alle') return -1; if (b === 'Alle') return 1
    return a.localeCompare(b)
  })
  sortedTimeKeys.forEach(timeKey => {
    let itemsInTimeGroup = timeGroups[timeKey]
    // Sort items within group by pinned, then order
    itemsInTimeGroup.sort((a, b) => {
      if (a.pinned && !b.pinned) return -1
      if (!a.pinned && b.pinned) return 1
      return a.order - b.order
    })
    if (groupByTags.value) {
      const tagMap = {}
      itemsInTimeGroup.forEach(todo => {
        if (!todo.tags || todo.tags.length === 0) {
          if (!tagMap['Keine Tags']) tagMap['Keine Tags'] = []
          tagMap['Keine Tags'].push(todo)
        } else {
          todo.tags.forEach(tag => {
            if (!tagMap[tag]) tagMap[tag] = []
            tagMap[tag].push(todo)
          })
        }
      })
      const subGroups = Object.keys(tagMap).sort().map(tk => ({ key: tk, items: tagMap[tk] }))
      finalGroups.push({ key: timeKey, subGroups })
    } else {
      finalGroups.push({ key: timeKey, items: itemsInTimeGroup })
    }
  })
  return finalGroups
})

const sortedTodos = computed(() => {
  if (aggregation.value !== 'none' || groupByTags.value) return []
  let result = [...filteredTodos.value]
  
  // Primary sort by pinned
  result.sort((a, b) => {
    if (a.pinned && !b.pinned) return -1
    if (!a.pinned && b.pinned) return 1
    
    // Secondary sort by chosen criteria
    if (activeSort.value.by === 'targetDate') {
      if (!a.targetDate && !b.targetDate) return 0
      if (!a.targetDate) return 1; if (!b.targetDate) return -1
      const timeA = new Date(a.targetDate).getTime(); const timeB = new Date(b.targetDate).getTime()
      return activeSort.value.dir === 'asc' ? timeA - timeB : timeB - timeA
    } else { 
      return a.order - b.order 
    }
  })
  return result
})

const sortedArchived = computed(() => {
  return [...archivedTodos.value].sort((a, b) => {
    if (!a.targetDate && !b.targetDate) return 0
    if (!a.targetDate) return 1
    if (!b.targetDate) return -1
    return new Date(b.targetDate).getTime() - new Date(a.targetDate).getTime()
  })
})

const isCurrentPeriod = (key) => {
  if (key === 'Kein Datum' || key === 'Alle') return false
  const now = new Date()
  
  if (aggregation.value === 'daily') {
    const y = now.getFullYear()
    const m = String(now.getMonth() + 1).padStart(2, '0')
    const d = String(now.getDate()).padStart(2, '0')
    return key === `${y}-${m}-${d}`
  }
  if (aggregation.value === 'weekly') {
    return key === `KW ${getWeekNumber(now)} (${now.getFullYear()})`
  }
  if (aggregation.value === 'monthly') {
    return key === now.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' })
  }
  return false
}

const scrollToCurrent = () => {
  setTimeout(() => {
    const el = document.getElementById('group-current')
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }, 100)
}

watch(aggregation, (newVal) => {
  if (newVal !== 'none') {
    scrollToCurrent()
  }
})

const allTags = computed(() => {
  const tags = new Set()
  todos.value.forEach(t => t.tags?.forEach(tag => tags.add(tag)))
  return Array.from(tags).sort()
})

const toggleTagFilter = (tag) => {
  const index = activeTags.value.indexOf(tag)
  if (index === -1) activeTags.value.push(tag)
  else activeTags.value.splice(index, 1)
}

const resetAll = () => {
  aggregation.value = 'none'
  groupByTags.value = false
  activeTags.value = []
  isExclusive.value = false
  activeSort.value = { by: 'order', dir: 'asc' }
  showCompleted.value = false
}

const handleReorder = (newList) => {
  if (aggregation.value !== 'none' || groupByTags.value || activeTags.value.length > 0 || activeSort.value.by !== 'order') return
  todos.value = newList
  onDragEnd()
}

const getDateFromWeekString = (weekStr) => {
  const match = weekStr.match(/KW (\d+) \((\d+)\)/);
  if (!match) return null;
  const week = parseInt(match[1]);
  const year = parseInt(match[2]);
  
  const simple = new Date(Date.UTC(year, 0, 1 + (week - 1) * 7));
  const dow = simple.getUTCDay();
  const ISOweekStart = simple;
  if (dow <= 4) {
    ISOweekStart.setUTCDate(simple.getUTCDate() - simple.getUTCDay() + 1);
  } else {
    ISOweekStart.setUTCDate(simple.getUTCDate() + 8 - simple.getUTCDay());
  }
  return ISOweekStart.toISOString().split('T')[0];
}

const getDateFromMonthString = (monthStr) => {
  const [monthName, year] = monthStr.split(' ');
  const months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
  const monthIndex = months.indexOf(monthName);
  if (monthIndex === -1) return null;
  const d = new Date(Date.UTC(parseInt(year), monthIndex, 1));
  return d.toISOString().split('T')[0];
}

const handleGroupChange = (evt, groupKey) => {
  if (evt.added) {
    const todoId = evt.added.element.id;
    let newDate = null;
    
    if (aggregation.value === 'daily') {
      newDate = groupKey;
    } else if (aggregation.value === 'weekly') {
      newDate = getDateFromWeekString(groupKey);
    } else if (aggregation.value === 'monthly') {
      newDate = getDateFromMonthString(groupKey);
    } else {
      if (groupKey === 'Kein Datum' || groupKey === 'Alle') newDate = '';
    }
    
    if (newDate !== null) {
      updateTodo(todoId, { targetDate: newDate });
    }
  }
}

const handleImported = () => {
  showBackupModal.value = false
  fetchData()
}

const userMenuContainer = ref(null)

onMounted(() => {
  applyVisualSettings()
  if (isAuthenticated.value) {
    fetchData()
    fetchNotes()
    fetchArchivedNotes()
    fetchWorkLogs()
  }
  
  // Close user menu on click away
  document.addEventListener('click', (e) => {
    if (userMenuContainer.value && !userMenuContainer.value.contains(e.target)) {
      showUserMenu.value = false
    }
  })
})

const toggleDarkMode = () => {
  isDarkMode.value = !isDarkMode.value
  localStorage.setItem('darkMode', isDarkMode.value)
}

watch(isDarkMode, (val) => {
  if (val) {
    document.documentElement.classList.add('dark-mode')
  } else {
    document.documentElement.classList.remove('dark-mode')
  }
}, { immediate: true })

const stats = computed(() => {
  const total = todos.value.length
  if (total === 0) return { total: 0, completed: 0, pending: 0, percent: 0, overdue: 0, topTags: [] }
  
  const completed = todos.value.filter(t => t.status === 'erledigt').length
  const pending = total - completed
  const percent = Math.round((completed / total) * 100)
  
  const now = new Date().toISOString().split('T')[0]
  const overdue = todos.value.filter(t => t.status !== 'erledigt' && t.targetDate && t.targetDate < now).length
  
  const tagCounts = {}
  todos.value.forEach(t => {
    if (t.tags) {
      t.tags.forEach(tag => {
        tagCounts[tag] = (tagCounts[tag] || 0) + 1
      })
    }
  })
  const topTags = Object.entries(tagCounts)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 5)
    
  return { total, completed, pending, percent, overdue, topTags }
})

const fetchNotes = async () => {
  try {
    const res = await axios.get(`${API_URL}/notes`)
    notes.value = res.data || {}
  } catch (err) { console.error("Error fetching notes", err) }
}

const fetchArchivedNotes = async () => {
  try {
    const res = await axios.get(`${API_URL}/notes/archive`)
    archivedNotes.value = res.data || {}
  } catch (err) { console.error("Error fetching archived notes", err) }
}

const saveNotes = async () => {
  isSavingNotes.value = true
  try {
    await axios.post(`${API_URL}/notes`, notes.value)
  } catch (err) { console.error("Error saving notes", err) }
  finally { isSavingNotes.value = false }
}

const saveArchivedNotes = async () => {
  try {
    await axios.post(`${API_URL}/notes/archive`, archivedNotes.value)
  } catch (err) { console.error("Error saving archived notes", err) }
}

// Debounced save for notes
let notesSaveTimeout = null
watch(notes, () => {
  if (notesSaveTimeout) clearTimeout(notesSaveTimeout)
  notesSaveTimeout = setTimeout(saveNotes, 1000)
}, { deep: true })

const updatePastNote = (dateKey, content) => {
  notes.value[dateKey] = content
  saveNotes()
}

const deleteNote = (key) => {
  if (confirm(`Möchtest du die Notiz vom ${new Date(key).toLocaleDateString()} wirklich archivieren?`)) {
    archivedNotes.value = { ...archivedNotes.value, [key]: notes.value[key] }
    const newNotes = { ...notes.value }
    delete newNotes[key]
    notes.value = newNotes
    saveNotes()
    saveArchivedNotes()
  }
}

const highlightNotesSearch = (text) => {
  if (!searchQuery.value || !text) return text
  const q = searchQuery.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const reg = new RegExp(`(${q})`, 'gi')
  return text.replace(reg, '<span class="highlight">$1</span>')
}

const fetchWorkLogs = async () => {
  try {
    const res = await axios.get(`${API_URL}/time`)
    workLogs.value = res.data || {}
    // Check if a timer is already running today and start the local ticker
    const log = workLogs.value[todayKey.value]
    if (log && log.isRunning && log.startTimeStamp) {
      elapsedTime.value = Date.now() - log.startTimeStamp
      startLocalTimer()
    }
  } catch (err) { console.error("Error fetching work logs", err) }
}

const saveWorkLogs = async () => {
  isSavingTime.value = true
  try {
    await axios.post(`${API_URL}/time`, workLogs.value)
  } catch (err) { console.error("Error saving work logs", err) }
  finally { isSavingTime.value = false }
}

const getWorkLog = (date) => {
  if (!workLogs.value[date]) {
    workLogs.value[date] = { 
      start: '', 
      end: '', 
      pause: userSettings.value.defaultPause || 0, 
      accumulatedMs: 0,
      isRunning: false, 
      startTimeStamp: null, 
      totalHours: 0 
    }
  }
  return workLogs.value[date]
}

const startTimer = () => {
  const log = getWorkLog(todayKey.value)
  const now = new Date()
  const timeStr = now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
  
  if (!log.start) log.start = timeStr
  
  log.isRunning = true
  log.startTimeStamp = Date.now()
  
  appLog('START', { date: todayKey.value, log })
  saveWorkLogs()
  startLocalTimer()
}

const stopTimer = () => {
  const log = getWorkLog(todayKey.value)
  const now = Date.now()
  
  if (log.isRunning && log.startTimeStamp) {
    const sessionMs = now - log.startTimeStamp
    log.accumulatedMs = (log.accumulatedMs || 0) + sessionMs
    appLog('STOP - Session ended', { sessionMs, newAccumulated: log.accumulatedMs })
  }
  
  log.end = new Date(now).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
  log.isRunning = false
  log.startTimeStamp = null
  
  calculateTotal(todayKey.value)
  saveWorkLogs()
  stopLocalTimer()
}

const calculateTotal = (date) => {
  const log = getWorkLog(date)
  
  // Total work is accumulatedMs + (running session) - pause
  let totalMs = log.accumulatedMs || 0
  if (log.isRunning && log.startTimeStamp) {
    totalMs += (Date.now() - log.startTimeStamp)
  }
  
  const pauseMs = (log.pause || 0) * 60000
  log.totalHours = Math.max(0, (totalMs - pauseMs) / 3600000)
  
  appLog('CALC_TOTAL', { date, totalHours: log.totalHours, accumulatedMs: log.accumulatedMs })
}

const updateTimeField = (date, field, value) => {
  const log = getWorkLog(date)
  log[field] = value
  
  // If user manually edits start/end, we try to approximate accumulatedMs
  // but this is optional. For now, manual edits of start/end override the 'accumulated' logic?
  // Let's make it so manual edits of start/end RE-CALCULATE accumulatedMs if not running
  if (!log.isRunning && (field === 'start' || field === 'end')) {
    if (log.start && log.end) {
      const [h1, m1] = log.start.split(':').map(Number)
      const [h2, m2] = log.end.split(':').map(Number)
      let diffMinutes = (h2 * 60 + m2) - (h1 * 60 + m1)
      if (diffMinutes < 0) diffMinutes += 24 * 60
      log.accumulatedMs = diffMinutes * 60000
      appLog('MANUAL_EDIT - Reset accumulatedMs', { diffMinutes })
    }
  }
  
  calculateTotal(date)
  saveWorkLogs()
}

const startLocalTimer = () => {
  stopLocalTimer()
  timerInterval = setInterval(() => {
    const log = workLogs.value[todayKey.value]
    if (log && log.isRunning && log.startTimeStamp) {
      // Current session time
      elapsedTime.value = Date.now() - log.startTimeStamp
    } else {
      elapsedTime.value = 0
    }
  }, 1000)
}

const stopLocalTimer = () => {
  if (timerInterval) clearInterval(timerInterval)
}

const getRunningTotalMs = (date) => {
  const log = workLogs.value[date]
  if (!log) return 0
  let totalMs = log.accumulatedMs || 0
  if (log.isRunning && log.startTimeStamp) {
    totalMs += (Date.now() - log.startTimeStamp)
  }
  return totalMs
}

const getRunningTotal = (date) => {
  const log = workLogs.value[date]
  if (!log) return 0
  const totalMs = getRunningTotalMs(date)
  const pauseMs = (log.pause || 0) * 60000
  return Math.max(0, (totalMs - pauseMs) / 3600000)
}

const getWeekSum = (dateStr) => {
  const date = new Date(dateStr)
  const day = date.getDay() || 7 // 1-7 (Mon-Sun)
  const start = new Date(date)
  start.setDate(date.getDate() - day + 1) // Monday
  
  let sum = 0
  for (let i = 0; i < 7; i++) {
    const d = new Date(start)
    d.setDate(start.getDate() + i)
    const key = d.toISOString().split('T')[0]
    sum += getRunningTotal(key)
  }
  return sum
}

const exportTimeLogs = () => {
  const sortedKeys = Object.keys(workLogs.value).sort((a, b) => b.localeCompare(a))
  let csv = "Datum;Start;Ende;Pause (Min);Gesamt (h)\n"
  sortedKeys.forEach(k => {
    const log = workLogs.value[k]
    const total = getRunningTotal(k).toFixed(2).replace('.', ',')
    csv += `${k};${log.start || ''};${log.end || ''};${log.pause || 0};${total}\n`
  })
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `arbeitszeiten-${new Date().toISOString().split('T')[0]}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

const getPastDate = (daysAgo) => {
  const d = new Date()
  d.setDate(d.getDate() - daysAgo)
  return d
}

const firstTimeEntryDate = computed(() => {
  const keys = Object.keys(workLogs.value).filter(k => workLogs.value[k].start || workLogs.value[k].totalHours > 0)
  if (keys.length === 0) return null
  return keys.sort()[0]
})

const filteredHistoryKeys = computed(() => {
  const start = timeFilter.value.from
  const end = timeFilter.value.to
  
  // We want to show all days in range, even if no log exists
  const keys = []
  let curr = new Date(end)
  const stop = new Date(start)
  
  while (curr >= stop) {
    keys.push(curr.toISOString().split('T')[0])
    curr.setDate(curr.getDate() - 1)
  }
  return keys
})

const liveTimerString = computed(() => {
  const log = workLogs.value[todayKey.value]
  if (!log || !log.isRunning || !log.startTimeStamp) return "00:00:00"
  
  // Explicitly reference elapsedTime to ensure reactivity
  // total = accumulated + (now - started)
  const totalMs = (log.accumulatedMs || 0) + Math.max(0, elapsedTime.value)
  
  const hh = Math.floor(totalMs / 3600000)
  const mm = Math.floor((totalMs % 3600000) / 60000)
  const ss = Math.floor((totalMs % 60000) / 1000)
  
  return [hh, mm, ss].map(v => v.toString().padStart(2, '0')).join(':')
})

const weeklyStats = computed(() => {
  const stats = []
  const now = new Date()
  
  for (let i = 0; i < 8; i++) {
    const d = new Date()
    d.setDate(now.getDate() - (i * 7))
    const day = d.getDay() || 7
    const monday = new Date(d)
    monday.setDate(d.getDate() - day + 1)
    
    let sum = 0
    for (let j = 0; j < 7; j++) {
      const dayDate = new Date(monday)
      dayDate.setDate(monday.getDate() + j)
      sum += getRunningTotal(dayDate.toISOString().split('T')[0])
    }
    
    stats.push({
      week: getWeekNumber(monday),
      year: monday.getFullYear(),
      total: sum
    })
  }
  return stats.reverse()
})

const pastNoteKeys = computed(() => {
  return Object.keys(notes.value)
    .filter(key => key < todayKey.value)
    .sort((a, b) => b.localeCompare(a))
})

const filteredPastNoteKeys = computed(() => {
  if (!searchQuery.value) return pastNoteKeys.value
  const q = searchQuery.value.toLowerCase()
  return pastNoteKeys.value.filter(key => {
    const content = (notes.value[key] || '').toLowerCase()
    const dateStr = new Date(key).toLocaleDateString('de-DE').toLowerCase()
    return content.includes(q) || dateStr.includes(q) || key.includes(q)
  })
})

const togglePastNote = (key) => {
  if (expandedPastNoteKeys.value.includes(key)) {
    expandedPastNoteKeys.value = expandedPastNoteKeys.value.filter(k => k !== key)
  } else {
    expandedPastNoteKeys.value.push(key)
  }
}

const pinnedTodos = computed(() => {
  return todos.value.filter(t => t.pinned && t.status !== 'erledigt')
})
</script>

<template>
  <div class="app-container">
    <!-- 1. DESKTOP NAV -->
    <div class="top-bar card slim desktop-nav">
      <div class="top-bar-inner">
        <!-- Logo Area -->
        <div class="logo-area">
          <div class="flattask-logo">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M4 6C4 4.89543 4.89543 4 6 4H18C19.1046 4 20 4.89543 20 6V18C20 19.1046 19.1046 20 18 20H6C4.89543 20 4 19.1046 4 18V6Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M4 10H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M4 14H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M9 17L11 19L15 15" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h1 class="logo">Flattask</h1>
          <div class="nav-icons-group">
            <button class="dark-mode-toggle" @click="toggleDarkMode" :title="isDarkMode ? 'Heller Modus' : 'Dunkler Modus'">
              <Sun v-if="isDarkMode" :size="14" />
              <Moon v-else :size="14" />
            </button>
            <button class="nav-icon-btn" :class="{ active: currentView === 'notes' }" @click="currentView = currentView === 'notes' ? 'main' : 'notes'" title="Notizen & Tagebuch">
              <StickyNote :size="16" />
            </button>
            <button class="nav-icon-btn" :class="{ active: currentView === 'time' }" @click="currentView = currentView === 'time' ? 'main' : 'time'" title="Arbeitszeiterfassung">
              <AlarmClock :size="16" />
              <span v-if="getWorkLog(todayKey).isRunning" class="mini-live-timer">{{ liveTimerString }}</span>
            </button>
          </div>
        </div>

        <!-- Scrollable Filters -->
        <div class="scrollable-filters">
          <div class="control-item tags-filter-container">
            <Filter :size="14" style="flex-shrink: 0;" />
            <div class="tags-chips small-chips">
              <span v-for="tag in allTags" :key="tag" class="tag-chip" :class="{ active: activeTags.includes(tag) }" @click="toggleTagFilter(tag)">{{ tag }}</span>
            </div>
            <label class="toggle-label mini" v-if="activeTags.length > 1">
              <input type="checkbox" v-model="isExclusive" /> Exklusiv
            </label>
          </div>

          <div class="control-item tags-chips small-chips">
            <span class="tag-chip" :class="{ active: aggregation === 'daily' }" @click="aggregation = aggregation === 'daily' ? 'none' : 'daily'" title="Täglich">
              <Clock :size="12" /> Tag
            </span>
            <span class="tag-chip" :class="{ active: aggregation === 'weekly' }" @click="aggregation = aggregation === 'weekly' ? 'none' : 'weekly'" title="Wöchentlich">
              <Clock :size="12" /> Woche
            </span>
            <span class="tag-chip" :class="{ active: aggregation === 'monthly' }" @click="aggregation = aggregation === 'monthly' ? 'none' : 'monthly'" title="Monatlich">
              <Clock :size="12" /> Monat
            </span>
            <button v-if="aggregation !== 'none'" class="pure-button mini-btn secondary" @click="scrollToCurrent" title="Zum aktuellen Zeitraum springen" style="padding: 0 0.4rem; height: 1.5rem; margin-left: 0.2rem; color: var(--primary);">
              <ArrowDown :size="14" />
            </button>
          </div>

          <div v-if="aggregation === 'none'" class="control-item tags-chips small-chips">
            <span class="tag-chip" :class="{ active: activeSort.by === 'targetDate' }" @click="activeSort.by = activeSort.by === 'targetDate' ? 'order' : 'targetDate'" title="Nach Datum sortieren">
              <SortAsc :size="12" /> Datum
            </span>
            <button v-if="activeSort.by === 'targetDate'" class="pure-button mini-btn secondary" style="padding: 0 0.4rem; height: 1.5rem; margin-left: -0.2rem;" @click="activeSort.dir = activeSort.dir === 'asc' ? 'desc' : 'asc'">
              {{ activeSort.dir === 'asc' ? '↑' : '↓' }}
            </button>
          </div>

          <div class="control-item tags-chips small-chips">
            <span class="tag-chip" :class="{ active: groupByTags }" @click="groupByTags = !groupByTags" title="Nach Tags gruppieren">
              <Layers :size="12" /> Tags
            </span>
            <span class="tag-chip" :class="{ active: showCompleted }" @click="showCompleted = !showCompleted" title="Erledigte Aufgaben anzeigen">
              <CheckCircle2 :size="12" /> Erledigte
            </span>
          </div>
        </div>

        <!-- Fixed Actions -->
        <div class="fixed-actions">
          <button v-if="aggregation !== 'none' || groupByTags || activeTags.length || showCompleted" class="pure-button mini-btn secondary" @click="resetAll" title="Reset">
            <X :size="12" />
          </button>

          <button class="pure-button mini-btn secondary" @click="currentView = currentView === 'stats' ? 'main' : 'stats'" :class="{ 'admin-active': currentView === 'stats' }" title="Statistik">
            <BarChart3 :size="14" />
          </button>

          <button class="pure-button mini-btn secondary" @click="showBackupModal = true" title="Backup & Archiv">
            <HardDrive :size="14" />
          </button>

          <div class="user-menu-container" ref="userMenuContainer">
            <button class="pure-button mini-btn secondary" :class="{ 'admin-active': showUserMenu }" @click="showUserMenu = !showUserMenu" title="Admin Settings">
              <User :size="14" />
            </button>
            <div v-if="showUserMenu" class="user-dropdown card slim" @click.stop>
              <div class="dropdown-header">Admin Menu</div>
              <button class="dropdown-item" @click="currentView = 'settings'; showUserMenu = false">
                <Settings :size="12" /> Einstellungen
              </button>
              <button class="dropdown-item" @click="currentView = 'profile'; showUserMenu = false">
                <User :size="12" /> Zugangsdaten
              </button>
              <button class="dropdown-item logout" @click="handleLogout(); showUserMenu = false">
                <LogOut :size="12" /> Abmelden
              </button>
            </div>
          </div>

          <button class="pure-button pure-button-primary mini-btn" @click="showNewForm = !showNewForm" title="Neu">
            <Plus :size="16" />
          </button>

          <div class="floating-search" :class="{ expanded: searchExpanded }">
            <input ref="searchInputRef" v-model="searchQuery" type="text" placeholder="Suchen..." class="expandable-input" :style="{ opacity: searchExpanded ? 1 : 0, pointerEvents: searchExpanded ? 'auto' : 'none' }" />
            <button class="search-icon-btn" @click="toggleSearch" title="Suchen">
              <HelpCircle :size="18" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 2. MOBILE NAV -->
    <div class="top-bar card slim mobile-nav">
      <div class="mobile-top-bar-inner">
        <button class="pure-button mini-btn secondary hamburger-btn" @click="mobileMenuOpen = true" title="Menü">
          <Menu :size="18" />
        </button>
        
        <div class="logo-group">
          <div class="flattask-logo mini">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M4 6C4 4.89543 4.89543 4 6 4H18C19.1046 4 20 4.89543 20 6V18C20 19.1046 19.1046 20 18 20H6C4.89543 20 4 19.1046 4 18V6Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M4 10H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M4 14H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M9 17L11 19L15 15" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h1 class="logo mobile-logo">Flattask</h1>
          <div class="mobile-nav-icons">
             <button class="dark-mode-toggle" @click="toggleDarkMode">
               <Sun v-if="isDarkMode" :size="20" />
               <Moon v-else :size="20" />
             </button>
             <button class="dark-mode-toggle" @click="currentView = currentView === 'notes' ? 'main' : 'notes'">
               <StickyNote :size="20" />
             </button>
             <button class="dark-mode-toggle" @click="currentView = currentView === 'time' ? 'main' : 'time'">
               <AlarmClock :size="20" />
               <span v-if="getWorkLog(todayKey).isRunning" class="mini-live-timer mobile">{{ liveTimerString }}</span>
             </button>
          </div>
        </div>
        
        <div class="mobile-quick-actions">
          <button class="pure-button pure-button-primary mini-btn" @click="showNewForm = !showNewForm" title="Neu">
            <Plus :size="16" />
          </button>
          
          <button class="search-icon-btn mobile-search-btn" @click="toggleSearch" title="Suchen">
            <HelpCircle :size="18" />
          </button>
        </div>
      </div>
      
      <!-- Mobile Search Input (Visible only when expanded) -->
      <div v-if="searchExpanded" class="mobile-search-bar">
         <input ref="searchInputRef" v-model="searchQuery" type="text" placeholder="Suchen..." class="expandable-input mobile-input" />
         <button class="pure-button mini-btn secondary" @click="searchExpanded = false">
            <X :size="14" />
         </button>
      </div>
    </div>

    <!-- Pinned Aggregation Section -->
    <div v-if="pinnedTodos.length > 0 && currentView === 'main'" class="pinned-aggregation card slim">
      <div class="pinned-header">
        <Pin :size="14" fill="currentColor" /> Angeheftete Aufgaben
      </div>
      <div class="pinned-items">
        <TodoItem v-for="todo in pinnedTodos" :key="'pinned-' + todo.id" :todo="todo" :all-tags="allTags" :can-drag="false" :search-query="searchQuery" @update="(updates) => updateTodo(todo.id, updates)" @delete="deleteTodo(todo.id)" />
      </div>
    </div>

    <!-- 3. MOBILE DRAWER OVERLAY -->
    <div class="mobile-drawer-overlay" :class="{ open: mobileMenuOpen }" @click="mobileMenuOpen = false">
      <div class="mobile-drawer-content card" @click.stop>
        <div class="drawer-header">
          <h2>Menü & Filter</h2>
          <button class="pure-button mini-btn secondary" @click="mobileMenuOpen = false"><X :size="16" /></button>
        </div>
        
        <div class="drawer-body">
           <!-- Filters duplicated from Desktop -->
           <div class="drawer-section">
             <h3>Filter & Tags</h3>
             <div class="tags-chips">
                <span v-for="tag in allTags" :key="tag" class="tag-chip" :class="{ active: activeTags.includes(tag) }" @click="toggleTagFilter(tag)">{{ tag }}</span>
             </div>
             <label class="toggle-label mini" v-if="activeTags.length > 1" style="margin-top: 0.5rem; display: inline-flex;">
                <input type="checkbox" v-model="isExclusive" /> Exklusiv
             </label>
           </div>
           
           <div class="drawer-section">
             <h3>Zeitraum</h3>
             <div class="tags-chips">
               <span class="tag-chip" :class="{ active: aggregation === 'daily' }" @click="aggregation = aggregation === 'daily' ? 'none' : 'daily'">Tag</span>
               <span class="tag-chip" :class="{ active: aggregation === 'weekly' }" @click="aggregation = aggregation === 'weekly' ? 'none' : 'weekly'">Woche</span>
               <span class="tag-chip" :class="{ active: aggregation === 'monthly' }" @click="aggregation = aggregation === 'monthly' ? 'none' : 'monthly'">Monat</span>
             </div>
             <button v-if="aggregation !== 'none'" class="pure-button mini-btn secondary drawer-btn" @click="scrollToCurrent(); mobileMenuOpen = false" style="margin-top: 0.5rem;">
               <ArrowDown :size="14" /> Zum aktuellen Zeitraum springen
             </button>
           </div>

           <div v-if="aggregation === 'none'" class="drawer-section">
             <h3>Sortierung</h3>
             <div class="tags-chips">
               <span class="tag-chip" :class="{ active: activeSort.by === 'targetDate' }" @click="activeSort.by = activeSort.by === 'targetDate' ? 'order' : 'targetDate'">Datum</span>
               <button v-if="activeSort.by === 'targetDate'" class="pure-button mini-btn secondary" style="padding: 0 0.4rem; height: 1.5rem;" @click="activeSort.dir = activeSort.dir === 'asc' ? 'desc' : 'asc'">
                 {{ activeSort.dir === 'asc' ? '↑ Aufsteigend' : '↓ Absteigend' }}
               </button>
             </div>
           </div>

           <div class="drawer-section">
             <h3>Ansicht</h3>
             <div class="tags-chips">
               <span class="tag-chip" :class="{ active: groupByTags }" @click="groupByTags = !groupByTags">Nach Tags gruppieren</span>
               <span class="tag-chip" :class="{ active: showCompleted }" @click="showCompleted = !showCompleted">Erledigte Aufgaben anzeigen</span>
             </div>
           </div>

           <div class="drawer-section drawer-actions">
             <button v-if="aggregation !== 'none' || groupByTags || activeTags.length || showCompleted" class="pure-button mini-btn secondary drawer-btn" @click="resetAll">
               <X :size="14" /> Alle Filter zurücksetzen
             </button>
             <button class="pure-button mini-btn secondary drawer-btn" @click="currentView = 'settings'; mobileMenuOpen = false">
                <Settings :size="14" /> Einstellungen
              </button>
             <button class="pure-button mini-btn secondary drawer-btn" @click="currentView = 'stats'; mobileMenuOpen = false">
                <BarChart3 :size="14" /> Statistik Dashboard
              </button>

              <button class="pure-button mini-btn secondary drawer-btn" @click="showBackupModal = true; mobileMenuOpen = false">
                <HardDrive :size="14" /> Backup & Archiv
              </button>

             <button class="pure-button mini-btn secondary drawer-btn" @click="currentView = 'profile'; mobileMenuOpen = false">
               <User :size="14" /> Zugangsdaten
             </button>

             <button class="pure-button mini-btn secondary drawer-btn" @click="handleLogout(); mobileMenuOpen = false">
               <LogOut :size="14" /> Abmelden
             </button>
           </div>
        </div>
      </div>
    </div>

    <div v-if="showNewForm" class="card new-todo-form">
      <form class="pure-form pure-form-stacked" @submit.prevent="addTodo">
        <fieldset>
          <legend>Neues Todo erstellen</legend>
          <div class="pure-g">
            <div class="pure-u-1 pure-u-md-1-2" style="padding-right: 1rem">
              <label>Name</label>
              <input v-model="newTodo.name" class="pure-u-1" type="text" maxlength="500" required />
            </div>
            <div class="pure-u-1 pure-u-md-1-4" style="padding-right: 1rem">
              <label>Zieldatum</label>
              <input v-model="newTodo.targetDate" class="pure-u-1" type="date" @input="(e) => e.target.blur()" />
            </div>
            <div class="pure-u-1 pure-u-md-1-4">
              <label>Tags</label>
              <input v-model="newTodoTagInput" class="pure-u-1" placeholder="Tag eingeben oder wählen" />
              <div class="suggested-tags">
                <span v-for="tag in allTags" :key="tag" class="tag-chip mini" @click="newTodoTagInput = (newTodoTagInput ? newTodoTagInput + ', ' : '') + tag">+ {{ tag }}</span>
              </div>
            </div>
          </div>
          <div style="margin-top: 1rem">
            <label>Beschreibung</label>
            <Editor v-model="newTodo.description" />
          </div>
          <div style="margin-top: 1rem; text-align: right;">
            <button type="button" class="pure-button" style="margin-right: 0.5rem" @click="showNewForm = false">Abbrechen</button>
            <button type="submit" class="pure-button pure-button-primary">Speichern</button>
          </div>
        </fieldset>
      </form>
    </div>

    <div v-if="currentView === 'main'" class="todo-list">
      <template v-if="aggregation === 'none' && !groupByTags">
        <draggable :model-value="sortedTodos" item-key="id" handle=".drag-handle" :animation="200" ghost-class="ghost" @update:model-value="handleReorder" :disabled="activeTags.length > 0 || activeSort.by !== 'order'">
          <template #item="{ element }">
            <TodoItem :todo="element" :all-tags="allTags" :can-drag="activeTags.length === 0 && activeSort.by === 'order'" :search-query="searchQuery" @update="(updates) => updateTodo(element.id, updates)" @delete="deleteTodo(element.id)" />
          </template>
        </draggable>
        <div v-if="sortedTodos.length === 0" class="empty-state">Keine Aufgaben gefunden.</div>
      </template>

      <template v-else>
        <div v-for="group in groupedTodos" :key="group.key" class="todo-group">
          <div v-if="group.key !== 'Alle'" class="group-header" :class="{ 'current-period': isCurrentPeriod(group.key) }" :id="isCurrentPeriod(group.key) ? 'group-current' : ''">
            <Calendar :size="16" /> {{ getGroupLabel(group.key) }}
            <span v-if="isCurrentPeriod(group.key)" class="badge current-badge">Aktuell</span>
          </div>
          <div v-if="groupByTags" class="tag-subgroups">
            <div v-for="sub in group.subGroups" :key="sub.key" class="tag-group">
              <div class="sub-header"><Tag :size="12" /> {{ sub.key }}</div>
              <div class="group-items">
                <draggable :model-value="sub.items" group="todos" item-key="id" handle=".drag-handle" :animation="200" ghost-class="ghost" @change="(evt) => handleGroupChange(evt, group.key)" :disabled="activeSort.by !== 'order' || activeTags.length > 0">
                  <template #item="{ element }">
                    <TodoItem :todo="element" :all-tags="allTags" :can-drag="activeSort.by === 'order' && activeTags.length === 0" :search-query="searchQuery" @update="(updates) => updateTodo(element.id, updates)" @delete="deleteTodo(element.id)" />
                  </template>
                </draggable>
              </div>
            </div>
          </div>
          <div v-else class="group-items">
            <draggable :model-value="group.items" group="todos" item-key="id" handle=".drag-handle" :animation="200" ghost-class="ghost" @change="(evt) => handleGroupChange(evt, group.key)" :disabled="activeSort.by !== 'order' || activeTags.length > 0">
              <template #item="{ element }">
                <TodoItem :todo="element" :all-tags="allTags" :can-drag="activeSort.by === 'order' && activeTags.length === 0" :search-query="searchQuery" @update="(updates) => updateTodo(element.id, updates)" @delete="deleteTodo(element.id)" />
              </template>
            </draggable>
          </div>
        </div>
        <div v-if="groupedTodos.length === 0" class="empty-state">Keine Aufgaben gefunden.</div>
      </template>
    </div>

    <div v-else-if="currentView === 'profile'" class="todo-list">
      <div class="card slim" style="max-width: 400px; margin: 0 auto; padding: 2rem;">
        <h2 class="text-center"><User :size="24" /> Zugangsdaten ändern</h2>
        <div class="pure-form pure-form-stacked">
          <label>Neuer Benutzername</label>
          <input v-model="profileData.username" type="text" class="pure-input-1" placeholder="Benutzername" />
          
          <label>Neues Passwort</label>
          <input v-model="profileData.password" type="password" class="pure-input-1" placeholder="Passwort" />
          
          <p v-if="profileStatus" :class="{ 'error-text': profileStatus.includes('Fehler'), 'text-success': profileStatus.includes('Erfolgreich') }" class="status-msg text-center">
            {{ profileStatus }}
          </p>
          
          <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
            <button class="pure-button pure-button-primary pure-input-1" @click="updateCredentials">Speichern</button>
            <button class="pure-button pure-input-1" @click="currentView = 'main'">Abbrechen</button>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="currentView === 'stats'" class="todo-list">
      <div class="stats-dashboard">
        <header class="dashboard-header">
          <h2><BarChart3 :size="24" /> Produktivitäts-Dashboard</h2>
          <button class="pure-button mini-btn" @click="currentView = 'main'">Zurück zur Liste</button>
        </header>

        <div class="stats-grid">
          <div class="card stat-card primary">
            <div class="stat-icon"><CheckCircle :size="32" /></div>
            <div class="stat-info">
              <span class="stat-label">Fortschritt</span>
              <span class="stat-value">{{ stats.percent }}%</span>
              <div class="stat-progress-bg">
                <div class="stat-progress-bar" :style="{ width: stats.percent + '%' }"></div>
              </div>
            </div>
          </div>

          <div class="card stat-card">
            <div class="stat-label">Aufgaben Gesamt</div>
            <div class="stat-value">{{ stats.total }}</div>
          </div>

          <div class="card stat-card success">
            <div class="stat-label">Erledigt</div>
            <div class="stat-value">{{ stats.completed }}</div>
          </div>

          <div class="card stat-card warning">
            <div class="stat-label">Offen</div>
            <div class="stat-value">{{ stats.pending }}</div>
          </div>

          <div v-if="stats.overdue > 0" class="card stat-card danger">
            <div class="stat-icon"><AlertCircle :size="20" /></div>
            <div class="stat-info">
              <span class="stat-label">Überfällig</span>
              <span class="stat-value">{{ stats.overdue }}</span>
            </div>
          </div>
        </div>

        <div class="dashboard-row">
          <div class="card chart-card">
            <h3><Tag :size="18" /> Top Tags</h3>
            <div class="tag-stats">
              <div v-for="[tag, count] in stats.topTags" :key="tag" class="tag-stat-item">
                <span class="tag-name">{{ tag }}</span>
                <div class="tag-bar-wrapper">
                  <div class="tag-bar" :style="{ width: (count / stats.total * 100) + '%' }"></div>
                </div>
                <span class="tag-count">{{ count }}</span>
              </div>
              <div v-if="stats.topTags.length === 0" class="empty-msg">Noch keine Tags vergeben.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Settings View -->
    <div v-if="currentView === 'settings' && isAuthenticated" class="settings-view">
      <div class="dashboard-header">
        <h2><Settings :size="24" /> Optische Einstellungen</h2>
        <button class="pure-button mini-btn secondary" @click="currentView = 'main'">Zurück zur Liste</button>
      </div>

      <div class="card settings-container">
        <div class="settings-grid">
          <!-- Row Height -->
          <div class="settings-item">
            <div class="settings-info">
              <label>Zeilenhöhe der Aufgaben</label>
              <span class="settings-desc">Passen Sie den vertikalen Abstand der Aufgaben an.</span>
            </div>
            <div class="settings-control">
              <input type="range" min="1.8" max="4.5" step="0.1" v-model="userSettings.rowHeight" class="range-slider" />
              <span class="value-display">{{ userSettings.rowHeight }}rem</span>
            </div>
          </div>

          <!-- Font Size -->
          <div class="settings-item">
            <div class="settings-info">
              <label>Schriftgröße</label>
              <span class="settings-desc">Größe des Aufgabentitels anpassen.</span>
            </div>
            <div class="settings-control">
              <input type="range" min="0.7" max="1.2" step="0.05" v-model="userSettings.fontSize" class="range-slider" />
              <span class="value-display">{{ userSettings.fontSize }}rem</span>
            </div>
          </div>

          <!-- Compact Mode -->
          <div class="settings-item">
            <div class="settings-info">
              <label>Kompakt-Modus</label>
              <span class="settings-desc">Reduziert Abstände und Ränder für maximale Übersicht.</span>
            </div>
            <div class="settings-control">
               <label class="toggle-switch">
                  <input type="checkbox" v-model="userSettings.compactMode" />
                  <span class="slider round"></span>
               </label>
               <span class="checkbox-label">Kompakte Darstellung</span>
            </div>
          </div>
        </div>

        <hr class="settings-divider" />

        <!-- Time Tracking Settings -->
        <div class="settings-section">
          <h3><AlarmClock :size="18" /> Zeiterfassung Einstellungen</h3>
          <div class="settings-grid">
            <div class="settings-item">
              <div class="settings-info">
                <label>Standard-Pause</label>
                <span class="settings-desc">Voreingestellte Pause in Minuten für neue Tage.</span>
              </div>
              <div class="settings-control">
                <input type="number" v-model="userSettings.defaultPause" class="mini-num-input" />
                <span class="value-display">Minuten</span>
              </div>
            </div>

            <div class="settings-item">
              <div class="settings-info">
                <label>Wochenarbeitszeit (Ziel)</label>
                <span class="settings-desc">Stunden pro Woche für die grafische Auswertung.</span>
              </div>
              <div class="settings-control">
                <input type="number" step="0.5" v-model="userSettings.weeklyGoal" class="mini-num-input" />
                <span class="value-display">Stunden</span>
              </div>
            </div>
          </div>
        </div>

        <div class="settings-preview mt-4">
          <h3>Vorschau</h3>
          <div class="preview-box" :class="{ 'compact': userSettings.compactMode }">
            <div class="preview-item">Beispiel Aufgabe 1</div>
            <div class="preview-item">Beispiel Aufgabe 2 (Aktiv)</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Notes View -->
    <div v-if="currentView === 'notes' && isAuthenticated" class="notes-view">
      <div class="dashboard-header">
        <h2><StickyNote :size="24" /> Notizen & Tagebuch</h2>
        <div style="display: flex; align-items: center; gap: 1rem;">
          <span v-if="isSavingNotes" class="save-indicator">Wird gespeichert...</span>
          <button class="pure-button mini-btn secondary" @click="currentView = 'main'">Zurück</button>
        </div>
      </div>

      <div class="notes-container">
        <!-- Today's Note -->
        <div class="card notes-editor-card">
          <div class="note-date-header">Heute, {{ new Date().toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' }) }}</div>
          <Editor v-model="notes[todayKey]" placeholder="Was beschäftigt dich heute? Schreibe hier deine Notizen..." />
        </div>

        <!-- Past Notes -->
        <div v-if="filteredPastNoteKeys.length > 0" class="past-notes-section">
          <h3>Vergangene Einträge <span v-if="searchQuery" class="search-result-count">({{ filteredPastNoteKeys.length }} Treffer)</span></h3>
          <div class="past-notes-list">
            <div v-for="key in filteredPastNoteKeys" :key="key" class="card past-note-card collapsible" :class="{ expanded: expandedPastNoteKeys.includes(key) }">
              <div class="note-header-clickable" @click="togglePastNote(key)">
                <div class="note-date-header mini">{{ new Date(key).toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' }) }}</div>
                <div v-if="!expandedPastNoteKeys.includes(key)" class="note-snippet" v-html="highlightNotesSearch((notes[key] || 'Kein Inhalt').substring(0, 150)) + '...'"></div>
                <div class="note-actions">
                  <button class="pure-button btn-icon small btn-danger" @click.stop="deleteNote(key)" title="Archivieren"><Trash2 :size="12" /></button>
                  <ChevronDown v-if="!expandedPastNoteKeys.includes(key)" :size="14" class="collapse-icon" />
                  <ChevronUp v-else :size="14" class="collapse-icon" />
                </div>
              </div>
              <div v-if="expandedPastNoteKeys.includes(key)" class="note-expanded-editor">
                <Editor v-model="notes[key]" @blur="saveNotes" />
              </div>
            </div>
          </div>
        </div>
        <div v-else class="empty-state">
          <template v-if="searchQuery">Keine Notizen für "{{ searchQuery }}" gefunden.</template>
          <template v-else>Noch keine vergangenen Einträge vorhanden.</template>
        </div>
      </div>
    </div>

    <!-- Time Tracking View -->
    <div v-if="currentView === 'time' && isAuthenticated" class="time-view">
      <div class="dashboard-header">
        <h2><AlarmClock :size="24" /> Arbeitszeiterfassung</h2>
        <div style="display: flex; align-items: center; gap: 1rem;">
          <span v-if="isSavingTime" class="save-indicator">Wird gespeichert...</span>
          <button class="pure-button mini-btn secondary" @click="exportTimeLogs">
             <Download :size="14" /> Export (CSV)
          </button>
          <button class="pure-button mini-btn secondary" @click="currentView = 'main'">Zurück</button>
        </div>
      </div>

      <div class="time-container">
        <!-- Today's Tracking -->
        <div class="card time-input-card" :class="{ 'timer-running': getWorkLog(todayKey).isRunning }">
          <div class="timer-header">
             <div class="time-info">
               <span class="date-label">Heute, {{ new Date().toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: 'long' }) }}</span>
               <div class="weekly-badge">Woche: {{ getWeekSum(todayKey).toFixed(2).replace('.', ',') }}h</div>
             </div>
             <div class="timer-display">
                <span class="main-total">{{ getRunningTotal(todayKey).toFixed(2).replace('.', ',') }}</span>
                <span class="unit-large">Stunden</span>
             </div>
          </div>

          <div class="timer-controls-area">
             <div v-if="getWorkLog(todayKey).isRunning" class="live-counter-large">{{ liveTimerString }}</div>
             <div class="timer-controls">
                <button v-if="!getWorkLog(todayKey).isRunning" class="pure-button start-btn" @click="startTimer">
                  <Clock :size="18" /> Start
                </button>
                <button v-else class="pure-button stop-btn" @click="stopTimer">
                  <X :size="18" /> Ende
                </button>
             </div>
          </div>

          <div class="manual-inputs">
             <div class="input-group">
               <label>Start</label>
               <input type="time" :value="getWorkLog(todayKey).start" @input="e => updateTimeField(todayKey, 'start', e.target.value)" />
             </div>
             <div class="input-group">
               <label>Ende</label>
               <input type="time" :value="getWorkLog(todayKey).end" @input="e => updateTimeField(todayKey, 'end', e.target.value)" />
             </div>
             <div class="input-group">
               <label>Pause (Min)</label>
               <input type="number" :value="getWorkLog(todayKey).pause" @input="e => updateTimeField(todayKey, 'pause', parseInt(e.target.value) || 0)" />
             </div>
          </div>
        </div>

        <!-- Weekly Chart -->
        <div class="card time-chart-card">
          <h3><BarChart3 :size="18" /> Arbeitsstunden pro Woche</h3>
          <div class="time-chart-container">
            <div v-for="stat in weeklyStats" :key="stat.week + '-' + stat.year" class="time-chart-bar-wrapper">
              <div class="time-chart-bar-outer" :title="stat.total.toFixed(2) + 'h'">
                <div class="time-chart-bar" :style="{ height: Math.min(100, (stat.total / (userSettings.weeklyGoal || 40)) * 100) + '%' }" :class="{ 'goal-met': stat.total >= (userSettings.weeklyGoal || 40) }">
                  <span class="bar-value" v-if="stat.total > 0">{{ stat.total.toFixed(1) }}</span>
                </div>
              </div>
              <span class="bar-label">KW {{ stat.week }}</span>
            </div>
          </div>
          <div class="chart-legend">
             <span class="legend-item"><span class="color-box goal"></span> Ziel ({{ userSettings.weeklyGoal || 40 }}h)</span>
             <span class="legend-item"><span class="color-box met"></span> Ziel erreicht</span>
          </div>
        </div>

        <!-- History -->
        <div class="time-history-section">
          <div class="history-header">
             <h3>Verlauf</h3>
             <div v-if="firstTimeEntryDate" class="first-entry-info">
               Erster Eintrag: <strong>{{ new Date(firstTimeEntryDate).toLocaleDateString('de-DE') }}</strong>
             </div>
          </div>
          
          <div class="history-filters card slim">
             <div class="filter-group">
                <label>Von</label>
                <input type="date" v-model="timeFilter.from" @input="(e) => e.target.blur()" />
             </div>
             <div class="filter-group">
                <label>Bis</label>
                <input type="date" v-model="timeFilter.to" @input="(e) => e.target.blur()" />
             </div>
          </div>

          <div class="card time-list-card">
            <div v-for="dateKey in filteredHistoryKeys" :key="dateKey" class="time-history-row-wrapper">
               <div class="time-history-row" :class="{ 'is-running': getWorkLog(dateKey).isRunning }">
                 <div class="history-date">
                   <span class="day-name">{{ new Date(dateKey).toLocaleDateString('de-DE', { weekday: 'short' }) }}</span>
                   <span class="day-num">{{ new Date(dateKey).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' }) }}</span>
                 </div>
                 <div class="history-times">
                   <input type="time" class="mini-time" :value="getWorkLog(dateKey).start" @input="e => updateTimeField(dateKey, 'start', e.target.value)" />
                   <span class="separator">-</span>
                   <input type="time" class="mini-time" :value="getWorkLog(dateKey).end" @input="e => updateTimeField(dateKey, 'end', e.target.value)" />
                 </div>
                 <div class="history-pause">
                   <input type="number" class="mini-num" title="Pause in Minuten" :value="getWorkLog(dateKey).pause" @input="e => updateTimeField(dateKey, 'pause', parseInt(e.target.value) || 0)" />
                   <span class="mini-unit">m</span>
                 </div>
                 <div class="history-total">
                   {{ getRunningTotal(dateKey).toFixed(2).replace('.', ',') }}h
                 </div>
               </div>
               <div v-if="new Date(dateKey).getDay() === 0" class="weekly-total-row">
                  Wochensumme: {{ getWeekSum(dateKey).toFixed(2).replace('.', ',') }}h
               </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <BackupModal :show="showBackupModal" :all-tags="allTags" :archived-notes="archivedNotes" @close="showBackupModal = false" @imported="handleImported" @revived="handleRevived" @notes-revived="fetchNotes(); fetchArchivedNotes()" />

    <!-- Login Modal -->
    <div v-if="!isAuthenticated" class="modal-overlay login-overlay">
      <div class="modal-content card slim login-card">
        <h2 class="text-center">Anmelden</h2>
        <div class="login-form">
          <div class="pure-form pure-form-stacked">
            <label>Benutzername</label>
            <input v-model="loginData.username" type="text" class="pure-input-1" />
            
            <label>Passwort</label>
            <input v-model="loginData.password" type="password" class="pure-input-1" @keyup.enter="handleLogin" />
            
            <p v-if="loginError" class="error-text">{{ loginError }}</p>
            
            <button class="pure-button pure-button-primary pure-input-1" @click="handleLogin">
              Anmelden
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.top-bar { padding: 0.3rem 0.75rem; margin-bottom: 0.5rem; position: sticky; top: 0.5rem; z-index: 1000; overflow: visible; }
.top-bar-inner { display: flex; align-items: center; gap: 0.5rem; width: 100%; min-height: 2rem; justify-content: space-between; }
.logo-area { flex-shrink: 0; }
.logo { font-size: 0.95rem; margin: 0; line-height: 2rem; white-space: nowrap; font-weight: 800; color: var(--primary); }
.nav-icons-group { display: flex; align-items: center; gap: 0.25rem; }
.nav-icon-btn { background: transparent; border: none; color: #9ca3af; cursor: pointer; padding: 0.3rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.nav-icon-btn:hover { background: #f3f4f6; color: var(--primary); }
.nav-icon-btn.active { color: var(--primary); background: rgba(59, 130, 246, 0.1); }
.dark-mode .nav-icon-btn:hover { background: #374151; }
.dark-mode-toggle { background: transparent; border: none; color: #9ca3af; cursor: pointer; padding: 0.3rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.dark-mode-toggle:hover { background: #f3f4f6; color: var(--primary); }
.dark-mode-toggle.mini { padding: 0.2rem; }
.dark-mode .dark-mode-toggle:hover { background: #374151; }

.scrollable-filters { flex: 1 1 auto; display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; overflow-x: auto; scrollbar-width: none; padding-bottom: 2px; }
.scrollable-filters::-webkit-scrollbar { display: none; }

.fixed-actions { flex-shrink: 0; display: flex; align-items: center; gap: 0.5rem; position: relative; padding-right: 36px; height: 100%; }

.control-item { display: flex; align-items: center; gap: 0.25rem; font-size: 0.7rem; color: #4b5563; white-space: nowrap; flex-shrink: 0; height: 1.8rem; }
.tags-filter-container { flex: 0 1 auto; max-width: 45%; display: flex; align-items: center; gap: 0.3rem; }
.small-chips { gap: 0.15rem !important; overflow-x: auto; white-space: nowrap; scrollbar-width: none; display: flex; align-items: center; height: 100%; }
.small-chips::-webkit-scrollbar { display: none; }
.small-chips .tag-chip { font-size: 0.6rem; padding: 0.05rem 0.35rem; border-radius: 2rem; flex-shrink: 0; display: flex; align-items: center; height: 1.4rem; }

.floating-search { position: absolute; right: 0; top: 50%; transform: translateY(-50%); display: flex; align-items: center; background: white; border-radius: 2rem; border: 1px solid var(--border-color); overflow: hidden; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); width: 32px; height: 32px; z-index: 100; box-sizing: border-box; }
.floating-search.expanded { width: 220px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); border-color: #3b82f6; }
.search-icon-btn { position: absolute; right: 0; top: 0; width: 30px; height: 30px; border-radius: 50%; background: transparent; border: none; display: flex; align-items: center; justify-content: center; color: #6b7280; cursor: pointer; padding: 0; flex-shrink: 0; outline: none; }
.expandable-input { width: 100%; border: none; background: transparent; padding: 0 30px 0 0.8rem; font-size: 0.85rem; outline: none; color: #374151; transition: opacity 0.2s; box-sizing: border-box; height: 100%; }

.toggle-label { cursor: pointer; display: flex; align-items: center; gap: 0.2rem; font-weight: 500; flex-shrink: 0; height: 100%; }
.toggle-label.mini { font-size: 0.65rem; color: var(--primary); background: #eff6ff; padding: 0 0.4rem; border-radius: 2rem; height: 1.4rem; }
.minimal-select { border: 1px solid var(--border-color); background: white; font-size: 0.7rem; color: #1f2937; padding: 0.1rem 0.25rem; border-radius: 2rem; height: 1.6rem; }
.mini-btn { padding: 0 0.4rem; font-size: 0.75rem; border-radius: 2rem !important; min-width: 2rem; display: flex; justify-content: center; align-items: center; height: 1.6rem; box-sizing: border-box; }
.mini-btn.secondary { background: #f3f4f6; color: #4b5563; border: 1px solid var(--border-color); }
.tag-chip { padding: 0.1rem 0.4rem; background: #f3f4f6; border-radius: 2rem; cursor: pointer; white-space: nowrap; transition: all 0.2s; border: 1px solid transparent; }
.tag-chip:hover { background: #e5e7eb; }
.tag-chip.active { background: #3b82f6; color: white; border-color: #2563eb; }
.suggested-tags { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.25rem; }
.tag-chip.mini { font-size: 0.6rem; padding: 0.05rem 0.25rem; color: #6b7280; }
.ghost { opacity: 0.5; background: #eef2ff; }
.todo-list { display: flex; flex-direction: column; gap: 0.3rem; }
.empty-state { text-align: center; padding: 2rem; color: #9ca3af; font-style: italic; font-size: 0.9rem; }
.todo-group { margin-bottom: 0.75rem; }
.group-header { padding: 0.5rem 1rem; background: var(--tag-bg); font-weight: 600; font-size: 0.95rem; color: var(--text-heading); display: flex; align-items: center; gap: 0.5rem; border-radius: 0.5rem; margin-bottom: 0.5rem; }
.group-header.current-period { background: var(--header-bg); border-left: 4px solid var(--primary); color: var(--primary); }
.current-badge { background: var(--primary); color: white; margin-left: 0.5rem; font-size: 0.7rem; padding: 0.1rem 0.5rem; border-radius: 2rem; }
.sub-header { font-size: 0.6rem; color: var(--text-muted); font-weight: 600; padding: 0.2rem 0.75rem; background: transparent; display: flex; align-items: center; gap: 0.25rem; border-bottom: none; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.5rem; margin-bottom: 0.1rem; }
.tag-group { margin-left: 0.2rem; border-left: 1px solid var(--border-color); }
.group-items { display: flex; flex-direction: column; gap: 0.25rem; padding: 0.1rem 0.3rem; }

/* Mobile specific classes hidden on desktop */
.mobile-nav { display: none; }
.mobile-drawer-overlay { display: none; }

/* Desktop-only styles - no changes needed here for desktop */

@media (max-width: 1024px) {
  .top-bar-inner {
    flex-wrap: wrap;
  }
  .scrollable-filters {
    order: 3;
    width: 100%;
    justify-content: flex-start;
  }
}

@media (max-width: 768px) {
  /* HIDE DESKTOP NAV COMPLETELY */
  .desktop-nav { display: none !important; }
  
  /* SHOW MOBILE NAV */
  .mobile-nav { 
    display: block !important; 
    position: -webkit-sticky !important;
    position: sticky !important;
    top: 0 !important;
    z-index: 1000 !important;
    padding: 0.4rem 0.75rem; 
    background: white;
  }
  .mobile-top-bar-inner { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; }
  
   .hamburger-btn { background: transparent; border: none; padding: 0.3rem; display: flex; align-items: center; justify-content: center; }
  .logo-group { display: flex; align-items: center; gap: 0.5rem; }
  .mobile-nav-icons { display: flex; align-items: center; gap: 0.25rem; }
  .mobile-logo { font-size: 1.25rem; font-weight: 800; color: var(--text-heading); margin: 0; letter-spacing: -0.025em; }
  .flattask-logo { color: var(--text-heading); display: flex; align-items: center; justify-content: center; margin-right: 0.5rem; }
  .flattask-logo.mini { margin-right: 0.4rem; }
  
  .mobile-quick-actions { display: flex; align-items: center; gap: 0.5rem; }
  .mobile-search-btn { position: relative; width: 32px; height: 32px; background: #f3f4f6; color: #4b5563; }
  
  .mobile-search-bar { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; padding: 0.2rem 0; animation: slideDown 0.2s ease-out; }
  .mobile-input { background: #f9fafb; border: 1px solid var(--border-color); border-radius: 2rem; padding: 0.4rem 1rem; flex: 1; height: 2rem; }
  
  @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

  /* MOBILE DRAWER OVERLAY */
  .mobile-drawer-overlay {
    display: block; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 2000;
    opacity: 0; pointer-events: none; transition: opacity 0.3s;
  }
  .mobile-drawer-overlay.open { opacity: 1; pointer-events: auto; }
  
  .mobile-drawer-content {
    position: absolute; top: 0; left: -100%; bottom: 0; width: 85%; max-width: 320px;
    background: white; box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    display: flex; flex-direction: column; transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 0;
  }
  .mobile-drawer-overlay.open .mobile-drawer-content { left: 0; }
  
  .drawer-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid var(--border-color); }
  .drawer-header h2 { margin: 0; font-size: 1.2rem; color: #1f2937; }
  
  .drawer-body { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 1.5rem; }
  
  .drawer-section h3 { margin: 0 0 0.5rem 0; font-size: 0.85rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
  .drawer-section .tags-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; overflow: visible; }
  .drawer-section .tag-chip { font-size: 0.85rem; padding: 0.4rem 0.8rem; height: auto; display: inline-block; }
  
  .drawer-actions { display: flex; flex-direction: column; gap: 0.5rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem; }
  .drawer-btn { justify-content: flex-start; padding: 0.75rem 1rem; font-size: 0.95rem; height: auto; gap: 0.75rem; background: #f9fafb; border: 1px solid var(--border-color); width: 100%; }
  
  .new-todo-form {
    position: -webkit-sticky !important;
    position: sticky !important;
    top: 3rem !important;
    z-index: 900 !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
    max-height: 80vh;
    overflow-y: auto;
  }

  .empty-state {
    padding: 1.5rem 0.75rem;
  }
}

/* Auth Styles */
.user-menu-container {
  position: relative;
}

.admin-active {
  background: #3b82f6 !important;
  color: white !important;
}

.user-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 0.5rem;
  width: 160px;
  z-index: 10000;
  padding: 0.5rem !important;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
  border: 1px solid #e5e7eb;
  background: white;
}

.dropdown-header {
  font-size: 0.65rem;
  font-weight: bold;
  color: #9ca3af;
  padding: 0.25rem 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.dropdown-item {
  background: none;
  border: none;
  padding: 0.6rem 0.75rem;
  font-size: 0.8rem;
  text-align: left;
  cursor: pointer;
  border-radius: 0.375rem;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  color: #374151;
  width: 100%;
  transition: all 0.1s;
}

.dropdown-item:hover {
  background: #eff6ff;
  color: #2563eb;
}

.dropdown-item.logout:hover {
  color: #ef4444;
  background: #fef2f2;
}

.login-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.85);
  backdrop-filter: blur(5px);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.login-card {
  width: 350px;
  max-width: 90%;
  padding: 2rem;
}

.error-text {
  color: #ff4444;
  font-size: 0.8rem;
  margin: 0.5rem 0;
}

.hint-text {
  font-size: 0.75rem;
  color: #888;
  margin-top: 1rem;
}

.text-success {
  color: #10b981;
  font-size: 0.8rem;
  margin: 0.5rem 0;
}

.text-center { text-align: center; }
.mt-2 { margin-top: 0.5rem; }
.stats-dashboard { padding: 1rem; }
.pinned-aggregation { 
  margin-bottom: 1rem; 
  border-left: 4px solid var(--primary); 
  padding: 0.5rem 0.75rem !important; 
  background: var(--bg-card) !important; 
  position: sticky; 
  top: 3.2rem; 
  z-index: 999; 
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
@media (max-width: 768px) {
  .pinned-aggregation { top: 2.8rem; }
}
.dark-mode .pinned-aggregation { background: var(--bg-card) !important; }
.pinned-header { font-size: 0.7rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem; padding-left: 0.2rem; }
.pinned-items { display: flex; flex-direction: column; gap: 0.25rem; }

.dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; }
.settings-view { padding: 1rem; max-width: 800px; margin: 0 auto; }
.settings-container { padding: 0; overflow: hidden; }
.settings-grid { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; }
.settings-section { padding: 0 1.5rem 1.5rem; }
.settings-section h3 { font-size: 1rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-heading); }
.settings-divider { border: 0; border-top: 1px solid var(--border-color); margin: 0; }

.settings-item { display: flex; justify-content: space-between; align-items: center; gap: 2rem; }
.settings-info { display: flex; flex-direction: column; gap: 0.25rem; }
.settings-info label { font-weight: 700; color: var(--text-heading); font-size: 0.95rem; }
.settings-desc { font-size: 0.8rem; color: var(--text-muted); }
.settings-control { display: flex; align-items: center; gap: 1rem; }
.mini-num-input { border: 1px solid var(--border-color); border-radius: 0.4rem; padding: 0.4rem; width: 60px; font-weight: 700; text-align: center; background: var(--bg-app); color: var(--text-heading); }
.checkbox-label { font-size: 0.85rem; color: var(--text-heading); font-weight: 500; }
.range-slider { flex: 1; accent-color: var(--primary); cursor: pointer; }
.value-display { font-family: monospace; font-size: 0.9rem; color: var(--primary); font-weight: 700; min-width: 3rem; text-align: right; }

/* Toggle Switch */
.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #d1d5db; transition: .4s; border-radius: 24px; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .slider { background-color: var(--primary); }
input:checked + .slider:before { transform: translateX(20px); }

.settings-preview { margin-top: 2rem; padding-top: 2rem; border-top: 2px dashed var(--border-color); }
.preview-box { border: 1px solid var(--border-color); border-radius: 0.5rem; overflow: hidden; background: var(--bg-app); }
.preview-item { height: var(--todo-row-height); padding: 0 1rem; display: flex; align-items: center; border-bottom: 1px solid var(--border-color); font-size: var(--todo-font-size); color: var(--text-main); }
.preview-box.compact .preview-item { padding: 0 0.5rem; }

/* Notes Styles */
.notes-view { padding: 1rem; max-width: 900px; margin: 0 auto; }
.notes-container { display: flex; flex-direction: column; gap: 2rem; }
.notes-editor-card { padding: 1.5rem; border-top: 4px solid var(--primary); }
.note-date-header { font-size: 0.75rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; }
.note-date-header.mini { font-size: 0.65rem; margin-bottom: 0.5rem; color: var(--text-muted); }
.save-indicator { font-size: 0.75rem; color: var(--text-muted); font-style: italic; }
.past-notes-section h3 { font-size: 1rem; color: var(--text-heading); margin-bottom: 1rem; }
.past-notes-list { display: flex; flex-direction: column; gap: 0.75rem; }
.past-note-card { padding: 0.75rem 1rem; border-left: 3px solid var(--border-color); transition: all 0.2s; cursor: default; }
.past-note-card.collapsible:hover { border-left-color: var(--primary); background: var(--bg-app); }
.past-note-card.expanded { border-left-color: var(--primary); padding: 1rem; }
.note-header-clickable { display: flex; align-items: center; gap: 1rem; cursor: pointer; flex: 1; }
.note-snippet { flex: 1; font-size: 0.85rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60%; }
.note-snippet :deep(.highlight) { background: #fde047; color: #000; border-radius: 2px; padding: 0 1px; }
.dark-mode .note-snippet :deep(.highlight) { background: #ca8a04; color: #fff; }
.note-actions { display: flex; align-items: center; gap: 0.5rem; margin-left: auto; }
.collapse-icon { color: var(--text-muted); }
.note-expanded-editor { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color); }
.search-result-count { font-size: 0.8rem; font-weight: normal; color: var(--text-muted); margin-left: 0.5rem; }

.dashboard-header h2 { margin: 0; display: flex; align-items: center; gap: 0.75rem; color: var(--text-heading); }

/* Time Tracking Styles */
.time-view { padding: 1rem; max-width: 800px; margin: 0 auto; }
.time-container { display: flex; flex-direction: column; gap: 2rem; }
.time-input-card { padding: 2rem; border-top: 4px solid #10b981; position: relative; }
.time-input-card.timer-running { border-top-color: #ef4444; background: #fffcfc; }
.dark-mode .time-input-card.timer-running { background: #2d1a1a; }

.timer-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; }
.timer-display { text-align: right; }
.main-total { font-size: 3rem; font-weight: 800; color: var(--text-heading); display: block; line-height: 1; }
.unit-large { font-size: 1rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; }

.timer-controls-area { display: flex; flex-direction: column; align-items: center; gap: 1rem; margin-bottom: 2rem; }
.live-counter-large { font-family: monospace; font-size: 2.5rem; font-weight: 700; color: #ef4444; letter-spacing: 2px; }

.timer-controls { display: flex; justify-content: center; }

.mini-live-timer { font-size: 0.65rem; font-weight: 700; color: #ef4444; font-family: monospace; margin-left: 0.3rem; background: rgba(239, 68, 68, 0.1); padding: 0.1rem 0.3rem; border-radius: 4px; }
.mini-live-timer.mobile { position: absolute; top: -5px; right: -5px; font-size: 0.55rem; padding: 0.05rem 0.2rem; }

.start-btn { background: #10b981; color: white; padding: 0.75rem 2.5rem; border-radius: 3rem; font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; border: none; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
.start-btn:hover { background: #059669; transform: translateY(-2px); }
.stop-btn { background: #ef4444; color: white; padding: 0.75rem 2.5rem; border-radius: 3rem; font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; border: none; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }
.stop-btn:hover { background: #dc2626; transform: translateY(-2px); }

.manual-inputs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); }
.input-group { display: flex; flex-direction: column; gap: 0.4rem; }
.input-group label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
.input-group input { border: 1px solid var(--border-color); border-radius: 0.5rem; padding: 0.5rem; font-size: 1rem; font-weight: 600; background: var(--bg-card); color: var(--text-heading); }

.history-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.first-entry-info { font-size: 0.75rem; color: var(--text-muted); background: var(--bg-app); padding: 0.2rem 0.6rem; border-radius: 2rem; border: 1px solid var(--border-color); }

.history-filters { display: flex; gap: 1.5rem; padding: 1rem !important; margin-bottom: 1rem; background: var(--bg-card); }
.filter-group { display: flex; align-items: center; gap: 0.75rem; }
.filter-group label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
.filter-group input { border: 1px solid var(--border-color); border-radius: 0.5rem; padding: 0.3rem 0.6rem; font-size: 0.85rem; background: var(--bg-app); color: var(--text-heading); }

.time-history-row { display: grid; grid-template-columns: 80px 1fr 100px 80px; align-items: center; padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); }
.time-history-row.is-running { background: rgba(239, 68, 68, 0.05); }
.history-times { display: flex; align-items: center; gap: 0.5rem; justify-content: center; }
.mini-time { border: 1px solid var(--border-color); border-radius: 0.25rem; padding: 0.2rem; font-size: 0.85rem; width: 85px; text-align: center; background: var(--bg-card); color: var(--text-heading); }
.history-pause { display: flex; align-items: center; gap: 0.2rem; justify-content: center; }
.mini-num { border: 1px solid var(--border-color); border-radius: 0.25rem; padding: 0.2rem; font-size: 0.85rem; width: 45px; text-align: center; background: var(--bg-card); color: var(--text-heading); }
.mini-unit { font-size: 0.7rem; color: var(--text-muted); }
.history-total { font-weight: 800; color: var(--text-heading); text-align: right; }

.weekly-total-row { background: #f3f4f6; padding: 0.5rem 1rem; font-size: 0.8rem; font-weight: 800; color: var(--primary); text-align: center; border-radius: 0.5rem; margin: 0.5rem 1rem; }
.dark-mode .weekly-total-row { background: #1f2937; }

.time-chart-card { padding: 1.5rem; }
.time-chart-container { display: flex; align-items: flex-end; justify-content: space-between; height: 150px; padding: 1rem 0; gap: 0.5rem; border-bottom: 2px solid var(--border-color); margin-bottom: 0.5rem; }
.time-chart-bar-wrapper { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; height: 100%; }
.time-chart-bar-outer { width: 100%; background: var(--bg-app); border-radius: 4px 4px 0 0; height: 100%; display: flex; align-items: flex-end; position: relative; }
.time-chart-bar { width: 100%; background: #93c5fd; border-radius: 4px 4px 0 0; position: relative; transition: height 0.5s ease-out; min-height: 2px; }
.time-chart-bar.goal-met { background: #10b981; }
.bar-value { position: absolute; top: -18px; left: 50%; transform: translateX(-50%); font-size: 0.65rem; font-weight: 700; color: var(--text-muted); white-space: nowrap; }
.bar-label { font-size: 0.65rem; font-weight: 700; color: var(--text-muted); white-space: nowrap; }

.chart-legend { display: flex; gap: 1rem; margin-top: 0.5rem; justify-content: center; }
.legend-item { display: flex; align-items: center; gap: 0.3rem; font-size: 0.7rem; color: var(--text-muted); }
.color-box { width: 10px; height: 10px; border-radius: 2px; }
.color-box.goal { background: #93c5fd; }
.color-box.met { background: #10b981; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
.stat-card { display: flex; flex-direction: column; justify-content: center; padding: 1.5rem; transition: transform 0.2s; }
.stat-card:hover { transform: translateY(-3px); }
.stat-card.primary { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; }
.stat-card.success { border-left: 4px solid #10b981; }
.stat-card.warning { border-left: 4px solid #f59e0b; }
.stat-card.danger { border-left: 4px solid #ef4444; color: #ef4444; }
.stat-card.primary .stat-label { color: rgba(255,255,255,0.8); }
.stat-card.primary .stat-value { color: white; }
.stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.025em; margin-bottom: 0.5rem; }
.stat-value { font-size: 2rem; font-weight: 800; color: var(--text-heading); }
.stat-icon { margin-bottom: 0.5rem; opacity: 0.8; }
.stat-progress-bg { background: rgba(255,255,255,0.2); height: 6px; border-radius: 10px; margin-top: 1rem; overflow: hidden; }
.stat-progress-bar { background: white; height: 100%; border-radius: 10px; transition: width 1s ease-out; }
.dashboard-row { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
.chart-card h3 { margin-top: 0; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem; }
.tag-stats { display: flex; flex-direction: column; gap: 1rem; }
.tag-stat-item { display: grid; grid-template-columns: 100px 1fr 40px; align-items: center; gap: 1rem; }
.tag-name { font-size: 0.85rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tag-bar-wrapper { background: var(--tag-bg); height: 8px; border-radius: 4px; overflow: hidden; }
.tag-bar { background: var(--primary); height: 100%; border-radius: 4px; }
.tag-count { font-size: 0.85rem; font-weight: 600; text-align: right; color: var(--text-muted); }
.empty-msg { text-align: center; color: var(--text-muted); font-style: italic; padding: 2rem; }

.dark-mode .stat-card:not(.primary) { background: #1f2937; }
.dark-mode .stat-progress-bg { background: rgba(255,255,255,0.1); }

@media (max-width: 640px) {
  .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
  .stat-value { font-size: 1.5rem; }
  .dashboard-header h2 { font-size: 1.25rem; }
}
</style>
