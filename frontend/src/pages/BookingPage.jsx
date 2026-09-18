import React, { useState, useEffect } from 'react';
import axios from 'axios';

const API = '/api/v1';

const STEPS = ['انتخاب خدمات', 'تاریخ و ساعت', 'اطلاعات شما', 'تایید نهایی'];

function BookingPage() {
  const [step, setStep] = useState(0);
  const [services, setServices] = useState([]);
  const [selectedServices, setSelectedServices] = useState([]);
  const [selectedDate, setSelectedDate] = useState('');
  const [selectedTime, setSelectedTime] = useState('');
  const [availableSlots, setAvailableSlots] = useState([]);
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({ first_name: '', last_name: '', phone: '', email: '', notes: '' });
  const [bookingResult, setBookingResult] = useState(null);

  useEffect(() => {
    axios.get(`${API}/services`).then(res => setServices(res.data.data || [])).catch(() => {});
  }, []);

  const toggleService = (service) => {
    setSelectedServices(prev =>
      prev.find(s => s.id === service.id)
        ? prev.filter(s => s.id !== service.id)
        : [...prev, service]
    );
  };

  const loadSlots = async (date) => {
    setSelectedDate(date);
    setLoading(true);
    try {
      const res = await axios.get(`${API}/slots`, {
        params: {
          date,
          service_id: selectedServices[0]?.id,
        },
      });
      setAvailableSlots(res.data.data?.slots || []);
    } catch {
      setAvailableSlots([]);
    }
    setLoading(false);
  };

  const submitBooking = async () => {
    setLoading(true);
    try {
      const res = await axios.post(`${API}/bookings`, {
        ...formData,
        items: selectedServices.map(s => ({ service_id: s.id, quantity: 1 })),
        booking_date: selectedDate,
        booking_time: selectedTime,
      });
      setBookingResult(res.data.data);
    } catch (err) {
      alert(err.response?.data?.error || 'خطا در ثبت نوبت');
    }
    setLoading(false);
  };

  const totalPrice = selectedServices.reduce((sum, s) => sum + parseFloat(s.price || 0), 0);
  const totalDuration = selectedServices.reduce((sum, s) => sum + parseInt(s.duration_minutes || 0), 0);

  const renderStep = () => {
    switch (step) {
      case 0:
        return (
          <div className="glass-card">
            <h3>خدمات مورد نظر خود را انتخاب کنید</h3>
            <div className="bbs-services-grid" style={{ marginTop: 16 }}>
              {services.map(service => (
                <div
                  key={service.id}
                  className={`service-card ${selectedServices.find(s => s.id === service.id) ? 'selected' : ''}`}
                  onClick={() => toggleService(service)}
                >
                  <h4>{service.name}</h4>
                  <p style={{ fontSize: 12, color: '#94a3b8', margin: '8px 0' }}>{service.description}</p>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
                    <span className="badge badge-confirmed">{service.duration_minutes} دقیقه</span>
                    <span style={{ color: '#6366f1', fontWeight: 600 }}>{service.price?.toLocaleString()} ریال</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        );

      case 1:
        return (
          <div className="glass-card">
            <h3>تاریخ و ساعت را انتخاب کنید</h3>
            <div className="calendar-container" style={{ marginTop: 16 }}>
              <div className="calendar">
                <CalendarWidget onSelectDate={loadSlots} />
              </div>
              <div className="time-slots">
                <h4 style={{ marginBottom: 12 }}>ساعت‌های موجود</h4>
                {loading ? (
                  <div className="spinner" />
                ) : (
                  <div className="time-slot-grid">
                    {availableSlots.length > 0 ? availableSlots.map(time => (
                      <div
                        key={time}
                        className={`time-slot ${selectedTime === time ? 'selected' : ''}`}
                        onClick={() => setSelectedTime(time)}
                      >
                        {time}
                      </div>
                    )) : (
                      <p style={{ color: '#94a3b8', fontSize: 13 }}>
                        {selectedDate ? 'ساعتی موجود نیست' : 'تاریخ را انتخاب کنید'}
                      </p>
                    )}
                  </div>
                )}
              </div>
            </div>
          </div>
        );

      case 2:
        return (
          <div className="glass-card">
            <h3>اطلاعات خود را وارد کنید</h3>
            <div style={{ marginTop: 16 }}>
              <div className="form-row">
                <div className="form-group" style={{ flex: 1 }}>
                  <label className="form-label">نام *</label>
                  <input
                    className="form-input"
                    value={formData.first_name}
                    onChange={e => setFormData({ ...formData, first_name: e.target.value })}
                    placeholder="نام"
                  />
                </div>
                <div className="form-group" style={{ flex: 1 }}>
                  <label className="form-label">نام خانوادگی *</label>
                  <input
                    className="form-input"
                    value={formData.last_name}
                    onChange={e => setFormData({ ...formData, last_name: e.target.value })}
                    placeholder="نام خانوادگی"
                  />
                </div>
              </div>
              <div className="form-group">
                <label className="form-label">تلفن همراه *</label>
                <input
                  className="form-input"
                  value={formData.phone}
                  onChange={e => setFormData({ ...formData, phone: e.target.value })}
                  placeholder="09123456789"
                />
              </div>
              <div className="form-group">
                <label className="form-label">ایمیل</label>
                <input
                  className="form-input"
                  value={formData.email}
                  onChange={e => setFormData({ ...formData, email: e.target.value })}
                  placeholder="email@example.com"
                />
              </div>
              <div className="form-group">
                <label className="form-label">توضیحات</label>
                <textarea
                  className="form-textarea"
                  value={formData.notes}
                  onChange={e => setFormData({ ...formData, notes: e.target.value })}
                  placeholder="توضیحات اضافی..."
                  rows={3}
                />
              </div>
            </div>
          </div>
        );

      case 3:
        return (
          <div className="glass-card">
            <h3>خلاصه نوبت</h3>
            <div style={{ marginTop: 16 }}>
              <div className="summary-item"><strong>خدمات:</strong> {selectedServices.map(s => s.name).join('، ')}</div>
              <div className="summary-item"><strong>مدت:</strong> {totalDuration} دقیقه</div>
              <div className="summary-item"><strong>تاریخ:</strong> {selectedDate}</div>
              <div className="summary-item"><strong>ساعت:</strong> {selectedTime}</div>
              <div className="summary-item"><strong>مبلغ:</strong> {totalPrice.toLocaleString()} ریال</div>
              <div className="summary-item"><strong>نام:</strong> {formData.first_name} {formData.last_name}</div>
              <div className="summary-item"><strong>تلفن:</strong> {formData.phone}</div>
              <hr style={{ borderColor: 'rgba(255,255,255,0.1)', margin: '16px 0' }} />
              <button className="btn btn-primary btn-block btn-lg" onClick={submitBooking} disabled={loading}>
                {loading ? <span className="spinner" /> : '✅ تایید و ثبت نوبت'}
              </button>
            </div>
          </div>
        );
    }
  };

  if (bookingResult) {
    return (
      <div className="booking-page">
        <div className="glass-card text-center" style={{ padding: 40 }}>
          <div style={{ fontSize: 64, marginBottom: 16 }}>✅</div>
          <h2>نوبت شما با موفقیت ثبت شد</h2>
          <div className="info-card" style={{ margin: '20px auto', maxWidth: 400 }}>
            <p>کد پیگیری: <code style={{ fontSize: 18 }}>{bookingResult.booking?.booking_code}</code></p>
            <p>تاریخ: {bookingResult.booking?.booking_date}</p>
            <p>ساعت: {bookingResult.booking?.booking_time}</p>
          </div>
          <Link to={`/track/${bookingResult.booking?.booking_code}`} className="btn btn-primary">
            پیگیری نوبت
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="booking-page">
      <div className="bbs-steps">
        {STEPS.map((label, i) => (
          <div
            key={i}
            className={`bbs-step ${i === step ? 'active' : ''} ${i < step ? 'completed' : ''}`}
            onClick={() => i < step && setStep(i)}
          >
            <span className="step-number">{i + 1}</span>
            {label}
          </div>
        ))}
      </div>

      {renderStep()}

      <div className="step-actions" style={{ display: 'flex', justifyContent: 'space-between', marginTop: 20 }}>
        {step > 0 && (
          <button className="btn btn-secondary" onClick={() => setStep(step - 1)}>
            ← قبلی
          </button>
        )}
        {step < 3 && (
          <button
            className="btn btn-primary"
            onClick={() => setStep(step + 1)}
            disabled={step === 0 && selectedServices.length === 0 || step === 1 && (!selectedDate || !selectedTime)}
            style={{ marginRight: 'auto' }}
          >
            بعدی →
          </button>
        )}
      </div>
    </div>
  );
}

function CalendarWidget({ onSelectDate }) {
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const dayNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

  const startOfMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1);
  const endOfMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 0);
  const startDay = startOfMonth.getDay();
  const daysInMonth = endOfMonth.getDate();

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const days = [];
  for (let i = 0; i < startDay; i++) days.push(null);
  for (let d = 1; d <= daysInMonth; d++) {
    const date = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), d);
    days.push({ day: d, disabled: date < today, date: date.toISOString().split('T')[0] });
  }

  return (
    <div>
      <div className="calendar-header">
        <button className="calendar-nav" onClick={() => setCurrentMonth(new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1))}>‹</button>
        <span>{currentMonth.toLocaleDateString('fa-IR', { year: 'numeric', month: 'long' })}</span>
        <button className="calendar-nav" onClick={() => setCurrentMonth(new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1))}>›</button>
      </div>
      <div className="calendar-grid">
        {dayNames.map(name => (
          <div key={name} className="calendar-day-header">{name}</div>
        ))}
        {days.map((d, i) => (
          d ? (
            <div
              key={i}
              className={`calendar-day ${d.disabled ? 'disabled' : ''}`}
              onClick={() => !d.disabled && onSelectDate(d.date)}
            >
              {d.day}
            </div>
          ) : (
            <div key={i} className="calendar-day empty" />
          )
        ))}
      </div>
    </div>
  );
}

export default BookingPage;
