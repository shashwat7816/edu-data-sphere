
import { useEffect } from 'react';

const Index = () => {
  useEffect(() => {
    // Redirect to the HTML landing page
    window.location.href = '/index.html';
  }, []);

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-100">
      <div className="text-center">
        <h1 className="text-4xl font-bold mb-4">Redirecting to EduDataSphere...</h1>
        <p className="text-xl text-gray-600">Please wait while we redirect you to the main page.</p>
      </div>
    </div>
  );
};

export default Index;
