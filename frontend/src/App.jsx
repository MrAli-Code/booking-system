import React, { useState, useEffect } from 'react';
import { Outlet, Link } from 'react-router-dom';
import axios from 'axios';

const API_BASE = '/api/v1';

function App() {
  const [theme, setTheme] = useState(localStorage.getItem('bbs-theme') || 'dark');
  const [branding, setBranding] = useState({
    name: 'Booking System',
    logo: null,
    primary_color: '#6366f1',
  });

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('bbs-theme', theme);
  }, [theme]);

  useEffect(() => {
    axios.get(`${API_BASE}/settings/branding`).catch(() => {});
  }, []);

  const toggleTheme = () => setTheme(prev => prev === 'dark' ? 'light' : 'dark');

  return (
    <div className="app">
      <header className="app-header glass-card">
        <div className="header-content">
          <Link to="/" className="logo">
            {branding.logo ? (
              <img src={branding.logo} alt={branding.name} height="32" />
            ) : (
              <span className="logo-text">{branding.name}</span>
            )}
          </Link>
          <nav className="nav-links">
            <Link to="/booking" className="nav-link">رزرو نوبت</Link>
            <Link to="/track" className="nav-link">پیگیری</Link>
            <Link to="/admin" className="nav-link">پنل مدیریت</Link>
            <button onClick={toggleTheme} className="theme-toggle btn btn-secondary btn-sm">
              {theme === 'dark' ? '🌙' : '☀️'}
            </button>
          </nav>
        </div>
      </header>
      <main className="main-content">
        <Outlet />
      </main>
    </div>
  );
}

export default App;
