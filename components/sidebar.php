  <aside id="userSidebar" class="w-64 md:w-64 bg-slate-950 text-slate-100 flex-col items-center py-6 gap-4 shadow-[0_24px_60px_rgba(15,23,42,0.35)] fixed md:sticky top-16 h-[calc(100vh-64px)] z-40 transition-transform duration-300 -translate-x-full md:translate-x-0 flex rounded-r-[2rem] md:rounded-r-[2rem]">
    <div class="w-full px-3">
      <div class="flex flex-col gap-2 rounded-2xl border border-slate-800 bg-slate-900/70 p-2 shadow-inner shadow-slate-950/50">
        <a href="dashboard.php?view=map" 
           class="flex w-full items-center justify-center rounded-xl px-3 py-3 text-[14px] font-bold uppercase tracking-[0.2em] transition-all <?php echo $view === 'map' ? 'bg-gradient-to-r from-blue-600 to-cyan-500 text-white shadow-lg shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>"
           title="Map View">
          Map
        </a>
        <a href="dashboard.php?view=reports" 
           class="flex w-full items-center justify-center rounded-xl px-3 py-3 text-[14px] font-bold uppercase tracking-[0.2em] transition-all <?php echo $view === 'reports' ? 'bg-gradient-to-r from-blue-600 to-cyan-500 text-white shadow-lg shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>"
           title="Report List">
          Reports
        </a>
        <a href="notifications.php" 
           class="flex w-full items-center justify-center rounded-xl px-3 py-3 text-[14px] font-bold uppercase tracking-[0.2em] transition-all <?php echo $view === 'notifications' ? 'bg-gradient-to-r from-blue-600 to-cyan-500 text-white shadow-lg shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>"
           title="Notifications">
          Notifications
        </a>
      </div>
    </div>
  </aside>