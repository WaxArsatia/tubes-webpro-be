# Laravel API Documentation

**Base URL**: `http://localhost:8000/api`  
**Auth**: Laravel Sanctum Bearer Token  
**Stack**: Laravel 12, JSON API, Role-based (admin/user)

## Authentication
- **Method**: `Authorization: Bearer {token}`
- **Token Expiry**: 24 hours (86400s)
- **Login behavior**: Revokes all previous tokens

## Response Format
**Success**: `{"message": "...", "data": {...}}`  
**Error**: `{"message": "...", "errors": {"field": ["error"]}}`  
**Status Codes**: 200 (OK), 201 (Created), 401 (Unauthorized), 422 (Validation), 500 (Server Error)

## API Endpoints

### 1. Health Check
`GET /api/health` - No auth required
```json
Response: {"status": "ok", "timestamp": "2025-12-10T12:34:56.000000Z"}
```

### 2. Register
`POST /api/register` - No auth required
```json
Request: {
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
Response (201): {
  "message": "User registered successfully",
  "data": {
    "user": {User Object},
    "access_token": "1|abc123...",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}
```
**Validation**: name (required, max:255), email (required, email, unique), password (required, min:8, confirmed)

### 3. Login
`POST /api/login` - No auth required
```json
Request: {"email": "john@example.com", "password": "password123"}
Response: {
  "message": "Login successful",
  "data": {
    "user": {User Object},
    "access_token": "2|xyz789...",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}
```
**Validation**: email (required, email), password (required)  
**Note**: Revokes all previous user tokens on success

### 4. Get Current User
`GET /api/user` - **Auth required**
```json
Response: {"data": {"user": {User Object}}}
```

### 5. Logout
`POST /api/logout` - **Auth required**
```json
Response: {"message": "Logout successful"}
```
**Note**: Only revokes current token

## User Object Schema
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "role": "user|admin",
  "email_verified_at": null,
  "created_at": "2025-12-10T12:00:00.000000Z",
  "updated_at": "2025-12-10T12:00:00.000000Z"
}
```
**Roles**: `admin` (elevated privileges), `user` (default)  
**Hidden fields**: password, remember_token

## React/TS Implementation

### API Client (Fetch)
```javascript
const API_URL = 'http://localhost:8000/api';

class ApiClient {
  constructor() {
    this.token = localStorage.getItem('access_token');
  }

  setToken(token) {
    this.token = token;
    token ? localStorage.setItem('access_token', token) : localStorage.removeItem('access_token');
  }

  async request(endpoint, options = {}) {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(this.token && { 'Authorization': `Bearer ${this.token}` }),
      ...options.headers,
    };

    const response = await fetch(`${API_URL}${endpoint}`, { ...options, headers });
    const data = await response.json();
    
    if (!response.ok) throw { status: response.status, ...data };
    return data;
  }

  get(endpoint) { return this.request(endpoint, { method: 'GET' }); }
  post(endpoint, body) { return this.request(endpoint, { method: 'POST', body: JSON.stringify(body) }); }
}

export default new ApiClient();
```

### Auth Context
```javascript
import { createContext, useState, useEffect, useContext } from 'react';
import apiClient from './api';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const checkAuth = async () => {
      const token = localStorage.getItem('access_token');
      if (!token) { setLoading(false); return; }
      
      try {
        const { data } = await apiClient.get('/user');
        setUser(data.user);
      } catch { apiClient.setToken(null); }
      finally { setLoading(false); }
    };
    checkAuth();
  }, []);

  const login = async (email, password) => {
    const res = await apiClient.post('/login', { email, password });
    apiClient.setToken(res.data.access_token);
    setUser(res.data.user);
    return res;
  };

  const register = async (name, email, password, password_confirmation) => {
    const res = await apiClient.post('/register', { name, email, password, password_confirmation });
    apiClient.setToken(res.data.access_token);
    setUser(res.data.user);
    return res;
  };

  const logout = async () => {
    try { await apiClient.post('/logout'); }
    catch (e) { console.error(e); }
    finally { apiClient.setToken(null); setUser(null); }
  };

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout, isAuthenticated: !!user, isAdmin: user?.role === 'admin' }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);
```

### Protected Route
```javascript
import { Navigate } from 'react-router-dom';
import { useAuth } from './AuthContext';

const ProtectedRoute = ({ children, requireAdmin = false }) => {
  const { user, loading, isAdmin } = useAuth();
  
  if (loading) return <div>Loading...</div>;
  if (!user) return <Navigate to="/login" replace />;
  if (requireAdmin && !isAdmin) return <Navigate to="/unauthorized" replace />;
  
  return children;
};
```

### Login Component
```javascript
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from './AuthContext';

const Login = () => {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ email: '', password: '' });
  const [errors, setErrors] = useState({});

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      await login(form.email, form.password);
      navigate('/dashboard');
    } catch (err) {
      if (err.status === 422) setErrors(err.errors || {});
      else alert(err.message);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <input name="email" value={form.email} onChange={e => setForm({...form, email: e.target.value})} />
      {errors.email && <span>{errors.email[0]}</span>}
      
      <input type="password" name="password" value={form.password} onChange={e => setForm({...form, password: e.target.value})} />
      {errors.password && <span>{errors.password[0]}</span>}
      
      <button type="submit">Login</button>
    </form>
  );
};
```

## TypeScript Types
```typescript
interface User {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'user';
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

interface AuthResponse {
  message: string;
  data: {
    user: User;
    access_token: string;
    token_type: 'Bearer';
    expires_in: number;
  };
}

interface ValidationError {
  message: string;
  errors: { [field: string]: string[] };
}
```

## Error Handling Patterns
- **422 Validation**: Display field-specific errors from `errors` object
- **401 Unauthorized**: Redirect to login, clear token
- **500 Server Error**: Check Laravel logs at `storage/logs/laravel.log`

## Key Notes
- CORS configured in Laravel `config/cors.php`
- Tokens stored in localStorage (consider httpOnly cookies for production)
- Register creates user with `role: 'user'` by default
- Admin role must be set manually via database or seeder
