(()=>{
  const q=(s,c=document)=>c.querySelector(s);
  const qa=(s,c=document)=>[...c.querySelectorAll(s)];

  q('[data-sidebar-toggle]')?.addEventListener('click',()=>q('#sidebar')?.classList.toggle('open'));
  document.addEventListener('click',e=>{
    const sb=q('#sidebar');
    if(sb?.classList.contains('open')&&!sb.contains(e.target)&&!e.target.closest('[data-sidebar-toggle]')) sb.classList.remove('open');
  });

  qa('[data-fill-login]').forEach(btn=>btn.addEventListener('click',()=>{
    const email=q('input[name=email]');
    if(email) email.value=btn.dataset.fillLogin;
  }));

  qa('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{
    const message=form.dataset.confirm||'Confirmar esta ação?';
    if(!window.confirm(message)) e.preventDefault();
  }));

  qa('[data-filter-table]').forEach(box=>{
    const search=q('[data-filter-search]',box);
    const status=q('[data-filter-status]',box);
    const rows=qa('[data-filter-row]',box);
    const empty=q('[data-filter-empty]',box);
    const apply=()=>{
      const term=(search?.value||'').trim().toLocaleLowerCase('pt-BR');
      const st=status?.value||'';
      let visible=0;
      rows.forEach(row=>{
        const okTerm=!term||(row.dataset.search||'').includes(term);
        const okStatus=!st||row.dataset.status===st;
        row.hidden=!(okTerm&&okStatus);
        if(!row.hidden) visible++;
      });
      if(empty) empty.hidden=visible!==0;
    };
    search?.addEventListener('input',apply);
    status?.addEventListener('change',apply);
  });

  const onlyDigits=value=>(value||'').replace(/\D/g,'');
  const applyMask=(kind,value)=>{
    let d=onlyDigits(value);
    if(kind==='cpf'){
      d=d.slice(0,11);
      return d.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2');
    }
    if(kind==='cnpj'){
      d=d.slice(0,14);
      return d.replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d{1,2})$/,'$1-$2');
    }
    if(kind==='cep'){
      d=d.slice(0,8);
      return d.replace(/(\d{5})(\d)/,'$1-$2');
    }
    if(kind==='phone'){
      d=d.slice(0,11);
      if(d.length<=10) return d.replace(/^(\d{2})(\d)/,'($1) $2').replace(/(\d{4})(\d)/,'$1-$2');
      return d.replace(/^(\d{2})(\d)/,'($1) $2').replace(/(\d{5})(\d)/,'$1-$2');
    }
    if(kind==='rg'){
      const raw=(value||'').toUpperCase().replace(/[^0-9A-Z]/g,'').slice(0,9);
      return raw.replace(/^(.{2})(.)/,'$1.$2').replace(/^(.{2})\.(.{3})(.)/,'$1.$2.$3').replace(/(.{3})(.)$/,'$1-$2');
    }
    if(kind==='cpfcnpj') return applyMask(d.length<=11?'cpf':'cnpj',d);
    return value;
  };

  qa('[data-mask]').forEach(input=>{
    const update=()=>{ input.value=applyMask(input.dataset.mask,input.value); };
    input.addEventListener('input',update);
    update();
  });

  // CEP: preenche endereço, cidade e UF automaticamente. ViaCEP com fallback BrasilAPI.
  qa('input[name="postal_code"]').forEach(input=>{
    let timer=null;
    let lastCep='';
    const form=input.closest('form')||document;
    const address=q('input[name="address"]',form);
    const city=q('input[name="city"]',form);
    const state=q('input[name="state"]',form);
    const lookup=async()=>{
      const cep=onlyDigits(input.value).slice(0,8);
      if(cep.length!==8||cep===lastCep) return;
      lastCep=cep;
      input.classList.add('cep-loading');
      try{
        let data=null;
        try{
          const r=await fetch('https://viacep.com.br/ws/'+cep+'/json/',{cache:'no-store'});
          if(r.ok){
            const j=await r.json();
            if(!j.erro) data={address:j.logradouro||'',city:j.localidade||'',state:j.uf||''};
          }
        }catch(_){}
        if(!data){
          try{
            const r=await fetch('https://brasilapi.com.br/api/cep/v1/'+cep,{cache:'no-store'});
            if(r.ok){
              const j=await r.json();
              data={address:j.street||'',city:j.city||'',state:j.state||''};
            }
          }catch(_){}
        }
        if(data){
          if(address && data.address) address.value=data.address;
          if(city && data.city) city.value=data.city;
          if(state && data.state) state.value=data.state;
          [address,city,state].forEach(el=>el?.dispatchEvent(new Event('input',{bubbles:true})));
          input.classList.add('cep-found');
          window.setTimeout(()=>input.classList.remove('cep-found'),1200);
        }
      }finally{
        input.classList.remove('cep-loading');
      }
    };
    input.addEventListener('input',()=>{
      window.clearTimeout(timer);
      timer=window.setTimeout(lookup,300);
    });
    input.addEventListener('blur',lookup);
  });

  // Campos editáveis no painel administrativo: duplo clique abre o input e ✓ salva.
  qa('[data-inline-field][data-inline-edit-url]').forEach(cell=>{
    if(cell.dataset.inlineBound==='1') return;
    cell.dataset.inlineBound='1';
    cell.addEventListener('dblclick',()=>{
      if(cell.classList.contains('editing')) return;
      const field=cell.dataset.inlineField||'';
      const type=cell.dataset.inlineType||'text';
      const mask=cell.dataset.inlineMask||'';
      const raw=cell.dataset.inlineRaw||'';
      const display=q('[data-inline-display]',cell);
      if(!display) return;

      cell.classList.add('editing');
      const editor=document.createElement('div');
      editor.className='admin-inline-editor';
      const input=document.createElement('input');
      input.type=type;
      input.value=raw;
      input.name=field;
      if(mask) input.dataset.mask=mask;
      const save=document.createElement('button');
      save.type='button';
      save.className='admin-inline-save';
      save.setAttribute('aria-label','Salvar alteração');
      save.textContent='✓';
      const cancel=document.createElement('button');
      cancel.type='button';
      cancel.className='admin-inline-cancel';
      cancel.setAttribute('aria-label','Cancelar edição');
      cancel.textContent='×';
      editor.append(input,save,cancel);
      display.hidden=true;
      cell.appendChild(editor);

      if(mask){
        const update=()=>{ input.value=applyMask(mask,input.value); };
        input.addEventListener('input',update);
        update();
      }
      input.focus();
      input.select?.();

      const close=()=>{
        editor.remove();
        display.hidden=false;
        cell.classList.remove('editing');
      };
      cancel.addEventListener('click',close);
      input.addEventListener('keydown',event=>{
        if(event.key==='Escape') close();
        if(event.key==='Enter'){event.preventDefault();save.click();}
      });
      save.addEventListener('click',async()=>{
        const csrf=q('input[name="_csrf"]')?.value||'';
        save.disabled=true;
        input.disabled=true;
        try{
          const body=new URLSearchParams({_csrf:csrf,field:field,value:input.value});
          const response=await fetch(cell.dataset.inlineEditUrl,{
            method:'POST',
            body,
            headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}
          });
          const payload=await response.json().catch(()=>({}));
          if(!response.ok||!payload.ok) throw new Error(payload.error||'Não foi possível salvar.');
          display.textContent=input.value||'—';
          cell.dataset.inlineRaw=input.value;
          cell.classList.add('saved');
          close();
          window.setTimeout(()=>window.location.reload(),350);
        }catch(error){
          window.alert(error?.message||'Não foi possível salvar.');
          input.disabled=false;
          save.disabled=false;
          input.focus();
        }
      });
    });
  });

  // Check-out somente após 15 minutos do check-in.
  qa('[data-checkout-countdown]').forEach(box=>{
    const button=q('[data-checkout-button]')||q('[data-checkout-button]',box.parentElement||document);
    const remaining=q('[data-checkout-remaining]',box);
    const unlockAt=Number(box.dataset.unlockAt||0);
    if(!unlockAt||!remaining||!button) return;
    const tick=()=>{
      const diff=Math.max(0,unlockAt-Date.now());
      if(diff<=0){
        box.hidden=true;
        button.disabled=false;
        button.textContent='Encerrar turno / check-out';
        return false;
      }
      const total=Math.ceil(diff/1000);
      const min=Math.floor(total/60);
      const sec=total%60;
      remaining.textContent=String(min).padStart(2,'0')+':'+String(sec).padStart(2,'0');
      button.disabled=true;
      button.textContent='Check-out liberado em '+remaining.textContent;
      return true;
    };
    if(tick()){
      const id=window.setInterval(()=>{if(!tick())window.clearInterval(id);},1000);
    }
  });

  const bindPasswordToggle=(input,button)=>{
    if(!input||!button||button.dataset.passwordBound==='1') return;
    button.dataset.passwordBound='1';
    button.addEventListener('click',()=>{
      const show=input.type==='password';
      input.type=show?'text':'password';
      button.setAttribute('aria-label',show?'Ocultar senha':'Mostrar senha');
      button.setAttribute('aria-pressed',show?'true':'false');
      button.classList.toggle('is-visible',show);
      input.focus({preventScroll:true});
    });
  };

  qa('.password-field').forEach(wrapper=>{
    bindPasswordToggle(q('input',wrapper),q('[data-password-toggle]',wrapper));
  });

  qa('input[type="password"]').forEach(input=>{
    if(input.closest('.password-field')) return;

    const wrapper=document.createElement('span');
    wrapper.className='password-field';
    input.parentNode?.insertBefore(wrapper,input);
    wrapper.appendChild(input);

    const button=document.createElement('button');
    button.type='button';
    button.className='password-toggle';
    button.dataset.passwordToggle='';
    button.setAttribute('aria-label','Mostrar senha');
    button.setAttribute('aria-pressed','false');
    button.innerHTML='<svg class="tp-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>';
    wrapper.appendChild(button);
    bindPasswordToggle(input,button);
  });

  qa('[data-category-modal]').forEach(modal=>{
    const openButton=q('[data-category-modal-open]');
    const form=q('[data-category-form]',modal);
    const input=q('input[name="name"]',form);
    const submit=q('[data-category-submit]',form);
    const feedback=q('[data-category-feedback]',form);
    const select=q('[data-category-select]');
    const notice=q('[data-category-created-notice]');
    let previousFocus=null;

    const setFeedback=(message='')=>{
      if(!feedback) return;
      feedback.textContent=message;
      feedback.hidden=!message;
    };

    const openModal=()=>{
      previousFocus=document.activeElement;
      setFeedback('');
      modal.hidden=false;
      document.body.classList.add('modal-open');
      window.setTimeout(()=>input?.focus(),0);
    };

    const closeModal=()=>{
      modal.hidden=true;
      document.body.classList.remove('modal-open');
      form?.reset();
      setFeedback('');
      previousFocus?.focus?.();
    };

    openButton?.addEventListener('click',openModal);
    qa('[data-category-modal-close]',modal).forEach(button=>button.addEventListener('click',closeModal));
    modal.addEventListener('click',event=>{ if(event.target===modal) closeModal(); });
    document.addEventListener('keydown',event=>{
      if(event.key==='Escape'&&!modal.hidden) closeModal();
    });

    form?.addEventListener('submit',async event=>{
      event.preventDefault();
      const name=(input?.value||'').trim();
      if(name.length<2){
        setFeedback('Informe um nome de categoria com pelo menos 2 caracteres.');
        input?.focus();
        return;
      }

      submit.disabled=true;
      setFeedback('');
      try{
        const response=await fetch(form.action,{
          method:'POST',
          body:new FormData(form),
          headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
        });
        const raw=await response.text();
        let payload={};
        try{ payload=raw?JSON.parse(raw):{}; }catch(_){ payload={}; }

        if(!response.ok||!payload.ok){
          const message=payload.error||(response.status===419
            ? 'Sua sessão expirou. Atualize a página e tente novamente.'
            : 'Não foi possível cadastrar a categoria.');
          throw new Error(message);
        }

        const category=payload.category||{};
        if(!category.id||!category.name||!select) throw new Error('A categoria foi salva, mas não foi possível selecioná-la.');

        let option=[...select.options].find(item=>String(item.value)===String(category.id));
        if(!option){
          option=new Option(String(category.name),String(category.id));
          select.add(option);
        }else{
          option.textContent=String(category.name);
        }

        select.value=String(category.id);
        select.dispatchEvent(new Event('change',{bubbles:true}));

        if(notice){
          notice.textContent='Categoria “'+String(category.name)+'” cadastrada e selecionada.';
          notice.hidden=false;
          window.setTimeout(()=>{ notice.hidden=true; },5000);
        }

        modal.hidden=true;
        document.body.classList.remove('modal-open');
        form.reset();
        setFeedback('');
        select.focus();
      }catch(error){
        setFeedback(error?.message||'Não foi possível cadastrar a categoria.');
        input?.focus();
      }finally{
        submit.disabled=false;
      }
    });
  });

  qa('form.register-form').forEach(form=>form.addEventListener('submit',event=>{
    const categories=q('.category-checks',form);
    if(categories&&!q('input[type="checkbox"]:checked',categories)){
      event.preventDefault();
      const first=q('input[type="checkbox"]',categories);
      categories.scrollIntoView({behavior:'smooth',block:'center'});
      first?.focus();
      window.alert('Selecione pelo menos uma função de interesse.');
    }
  }));

  qa('input[name="acceptance_mode"]').forEach(input=>input.addEventListener('change',()=>{
    qa('.choice-card').forEach(card=>card.classList.toggle('selected',!!q('input',card)?.checked));
  }));

  qa('[data-global-search]').forEach(form=>{
    const input=q('[data-global-search-input]',form);
    const dropdown=q('[data-global-search-dropdown]',form);
    if(!input||!dropdown) return;

    let timer=null;
    let controller=null;
    let activeIndex=-1;

    const close=()=>{
      dropdown.hidden=true;
      dropdown.replaceChildren();
      activeIndex=-1;
    };

    const itemNodes=()=>qa('.global-search-suggestion',dropdown);

    const setActive=index=>{
      const items=itemNodes();
      if(!items.length){activeIndex=-1;return;}
      activeIndex=(index+items.length)%items.length;
      items.forEach((item,i)=>item.classList.toggle('active',i===activeIndex));
      items[activeIndex]?.scrollIntoView({block:'nearest'});
    };

    const render=(items,query)=>{
      dropdown.replaceChildren();
      activeIndex=-1;

      const head=document.createElement('div');
      head.className='global-search-suggestion-head';
      head.textContent='Resultados para “'+query+'”';
      dropdown.appendChild(head);

      if(!items.length){
        const empty=document.createElement('div');
        empty.className='global-search-suggestion-empty';
        empty.textContent='Nenhum resultado encontrado.';
        dropdown.appendChild(empty);
      }else{
        items.slice(0,8).forEach(item=>{
          const link=document.createElement('a');
          link.className='global-search-suggestion';
          link.href=item.url||'#';

          const badge=document.createElement('span');
          badge.className='global-search-suggestion-type';
          badge.textContent=item.type||'Resultado';

          const copy=document.createElement('span');
          copy.className='global-search-suggestion-copy';
          const title=document.createElement('strong');
          title.textContent=item.title||'';
          const subtitle=document.createElement('small');
          subtitle.textContent=item.subtitle||'';
          copy.append(title,subtitle);

          const meta=document.createElement('span');
          meta.className='global-search-suggestion-meta';
          meta.textContent=item.meta||'';

          link.append(badge,copy,meta);
          dropdown.appendChild(link);
        });
      }

      const all=document.createElement('button');
      all.type='submit';
      all.className='global-search-see-all';
      all.textContent='Ver todos os resultados →';
      dropdown.appendChild(all);
      dropdown.hidden=false;
    };

    const search=async()=>{
      const term=input.value.trim();
      if(term.length<2){close();return;}
      controller?.abort();
      controller=new AbortController();
      try{
        const base=window.TP_BASE||'';
        const response=await fetch(base+'/buscar?q='+encodeURIComponent(term)+'&format=json',{
          headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
          cache:'no-store',
          signal:controller.signal
        });
        if(!response.ok) throw new Error('search failed');
        const payload=await response.json();
        if(input.value.trim()!==term) return;
        render(Array.isArray(payload?.data?.items)?payload.data.items:[],term);
      }catch(error){
        if(error?.name!=='AbortError') close();
      }
    };

    input.addEventListener('input',()=>{
      window.clearTimeout(timer);
      timer=window.setTimeout(search,220);
    });
    input.addEventListener('focus',()=>{
      if(input.value.trim().length>=2) search();
    });
    input.addEventListener('keydown',event=>{
      if(dropdown.hidden) return;
      const items=itemNodes();
      if(event.key==='ArrowDown'){
        event.preventDefault();
        setActive(activeIndex+1);
      }else if(event.key==='ArrowUp'){
        event.preventDefault();
        setActive(activeIndex-1);
      }else if(event.key==='Enter'&&activeIndex>=0&&items[activeIndex]){
        event.preventDefault();
        window.location.href=items[activeIndex].href;
      }else if(event.key==='Escape'){
        close();
      }
    });
    form.addEventListener('submit',event=>{
      if(input.value.trim().length<2){
        event.preventDefault();
        input.focus();
        close();
      }
    });
    dropdown.addEventListener('mousedown',event=>event.stopPropagation());
    document.addEventListener('mousedown',event=>{
      if(!form.contains(event.target)) close();
    });
  });

  const notificationRoot=q('[data-notifications]');
  if(notificationRoot){
    const toggle=q('[data-notifications-toggle]',notificationRoot);
    const panel=q('[data-notifications-panel]',notificationRoot);
    const badge=q('[data-notification-badge]',notificationRoot);
    const summary=q('[data-notification-summary]',notificationRoot);
    const list=q('[data-notification-list]',notificationRoot);

    const knownIds=new Set(qa('[data-notification-id]',notificationRoot).map(el=>String(el.dataset.notificationId||'')).filter(Boolean));
    let initialized=true;
    let audioUnlocked=false;
    const unlockAudio=()=>{audioUnlocked=true;};
    document.addEventListener('pointerdown',unlockAudio,{once:true});

    const playNotificationPop=()=>{
      if(!audioUnlocked) return;
      try{
        const Ctx=window.AudioContext||window.webkitAudioContext;
        if(!Ctx) return;
        const ctx=new Ctx();
        const gain=ctx.createGain();
        gain.gain.setValueAtTime(.0001,ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(.15,ctx.currentTime+.01);
        gain.gain.exponentialRampToValueAtTime(.0001,ctx.currentTime+.18);
        const osc=ctx.createOscillator();
        osc.type='sine';
        osc.frequency.setValueAtTime(720,ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(980,ctx.currentTime+.12);
        osc.connect(gain); gain.connect(ctx.destination);
        osc.start(); osc.stop(ctx.currentTime+.19);
        osc.onended=()=>ctx.close();
      }catch(_){}
    };

    const showNotificationToast=item=>{
      let stack=q('[data-notification-toast-stack]');
      if(!stack){
        stack=document.createElement('div');
        stack.className='notification-toast-stack';
        stack.dataset.notificationToastStack='';
        document.body.appendChild(stack);
      }
      const toast=document.createElement('a');
      toast.className='notification-toast';
      toast.href=item.open_url||((window.TP_BASE||'')+'/notificacoes');
      const title=document.createElement('strong');
      title.textContent=item.title||'Nova notificação';
      const body=document.createElement('span');
      body.textContent=item.body||'';
      toast.append(title,body);
      stack.appendChild(toast);
      requestAnimationFrame(()=>toast.classList.add('show'));
      window.setTimeout(()=>{toast.classList.remove('show');window.setTimeout(()=>toast.remove(),220);},6500);
    };

    const announceNewItems=items=>{
      const fresh=items.filter(item=>!item.read_at && item.id!=null && !knownIds.has(String(item.id)));
      items.forEach(item=>{if(item.id!=null)knownIds.add(String(item.id));});
      if(!fresh.length) return;
      notificationRoot.classList.remove('notification-new');
      void notificationRoot.offsetWidth;
      notificationRoot.classList.add('notification-new');
      window.setTimeout(()=>notificationRoot.classList.remove('notification-new'),5000);
      fresh.slice(0,2).forEach(showNotificationToast);
      playNotificationPop();
    };

    const setOpen=open=>{
      if(!panel||!toggle) return;
      panel.hidden=!open;
      toggle.setAttribute('aria-expanded',open?'true':'false');
      notificationRoot.classList.toggle('open',open);
    };

    toggle?.addEventListener('click',event=>{
      event.stopPropagation();
      setOpen(panel?.hidden!==false);
    });
    panel?.addEventListener('click',event=>event.stopPropagation());
    document.addEventListener('click',()=>setOpen(false));
    document.addEventListener('keydown',event=>{if(event.key==='Escape')setOpen(false);});

    const iconText=type=>{
      if(type==='matching_shift') return 'V';
      if(type==='invitation') return 'C';
      if(type==='application') return 'P';
      if(type==='application_approved') return '✓';
      if(type==='application_rejected') return '!';
      return '•';
    };

    const renderNotifications=data=>{
      if(!data||!badge||!list) return;
      const unread=Number(data.unread||0);
      badge.textContent=unread>99?'99+':String(unread);
      badge.hidden=unread<=0;
      if(summary) summary.textContent=unread+' não lida(s)';

      list.replaceChildren();
      const items=Array.isArray(data.items)?data.items:[];
      announceNewItems(items);
      if(!items.length){
        const empty=document.createElement('div');
        empty.className='notification-empty';
        empty.textContent='Nenhuma notificação por enquanto.';
        list.appendChild(empty);
        return;
      }

      items.forEach(item=>{
        const link=document.createElement('a');
        link.className='notification-item'+(item.read_at?'':' unread');
        if(item.id!=null) link.dataset.notificationId=String(item.id);
        link.href=item.open_url||((window.TP_BASE||'')+'/notificacoes');

        const icon=document.createElement('span');
        icon.className='notification-item-icon notification-item-letter';
        icon.textContent=iconText(item.type||'');

        const copy=document.createElement('span');
        copy.className='notification-item-copy';
        const title=document.createElement('strong');
        title.textContent=item.title||'Notificação';
        const body=document.createElement('span');
        body.textContent=item.body||'';
        const time=document.createElement('small');
        time.textContent=(item.created_at||'').replace(/^([0-9]{4})-([0-9]{2})-([0-9]{2}) /,'$3/$2/$1 ');
        copy.append(title,body,time);
        link.append(icon,copy);
        list.appendChild(link);
      });
    };

    const refreshNotifications=async()=>{
      if(document.hidden) return;
      try{
        const response=await fetch((window.TP_BASE||'')+'/notificacoes/feed',{
          headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
          cache:'no-store'
        });
        if(!response.ok) return;
        const payload=await response.json();
        if(payload?.ok) renderNotifications(payload.data);
      }catch(_){}
    };

    window.setInterval(refreshNotifications,20000);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshNotifications();});
  }


  // Chat de suporte: busca incremental de novas interações sem recarregar a página.
  qa('[data-support-thread]').forEach(thread=>{
    const endpoint=thread.getAttribute('data-support-updates-url')||'';
    if(!endpoint) return;

    const statusBadge=q('[data-support-status]');
    const composer=q('[data-support-composer]');
    const closedNote=q('[data-support-closed-note]');
    const statusSelect=q('[data-support-status-select]');
    const statusLabels={
      open:'Aberto',
      in_progress:'Em atendimento',
      answered:'Respondido',
      closed:'Encerrado'
    };
    const statusClasses={
      open:'open',
      in_progress:'progress',
      answered:'answered',
      closed:'closed'
    };

    let lastMessageId=Number(thread.dataset.supportLastMessageId||0);
    let polling=false;
    let timer=null;
    let failures=0;

    const applyStatus=status=>{
      if(!status) return;
      thread.dataset.supportTicketStatus=status;
      if(statusBadge){
        statusBadge.classList.remove('open','progress','answered','closed');
        statusBadge.classList.add(statusClasses[status]||'open');
        statusBadge.textContent=statusLabels[status]||status;
      }
      if(statusSelect && statusSelect.value!==status) statusSelect.value=status;
      if(closedNote){
        const closed=status==='closed';
        closedNote.hidden=!closed;
        if(composer) composer.hidden=closed;
      }
    };

    const createMessage=message=>{
      if(!message || !Number.isFinite(Number(message.id))) return null;
      if(q('[data-support-message-id="'+Number(message.id)+'"]',thread)) return null;

      const article=document.createElement('article');
      article.className='support-message '+(message.from_support?'support':'requester')+' support-message-new';
      article.dataset.supportMessageId=String(message.id);

      const author=document.createElement('div');
      author.className='support-message-author';

      const avatar=document.createElement('span');
      avatar.className='avatar-sm';
      avatar.textContent=message.author_initial||'?';

      const identity=document.createElement('div');
      const strong=document.createElement('strong');
      strong.textContent=message.author||'Usuário';
      const small=document.createElement('small');
      small.textContent=message.meta||'';
      identity.append(strong,small);
      author.append(avatar,identity);

      const body=document.createElement('p');
      body.textContent=message.body||'';

      article.append(author,body);
      return article;
    };

    const appendMessages=messages=>{
      if(!Array.isArray(messages) || !messages.length) return;
      let appended=0;
      messages.forEach(message=>{
        const article=createMessage(message);
        if(!article) return;
        thread.appendChild(article);
        lastMessageId=Math.max(lastMessageId,Number(message.id)||0);
        appended++;
        window.setTimeout(()=>article.classList.remove('support-message-new'),1200);
      });
      thread.dataset.supportLastMessageId=String(lastMessageId);
      if(appended && document.visibilityState==='visible'){
        const newest=thread.lastElementChild;
        const rect=newest?.getBoundingClientRect();
        if(rect && rect.top<window.innerHeight*1.15){
          newest.scrollIntoView({behavior:'smooth',block:'nearest'});
        }
      }
    };

    const schedule=delay=>{
      window.clearTimeout(timer);
      timer=window.setTimeout(poll,delay);
    };

    const poll=async()=>{
      if(polling){
        schedule(1500);
        return;
      }
      if(document.visibilityState==='hidden'){
        schedule(5000);
        return;
      }

      polling=true;
      try{
        const separator=endpoint.includes('?')?'&':'?';
        const response=await fetch(endpoint+separator+'after='+encodeURIComponent(lastMessageId),{
          method:'GET',
          credentials:'same-origin',
          cache:'no-store',
          headers:{'Accept':'application/json'}
        });
        if(!response.ok) throw new Error('support polling failed');
        const payload=await response.json();
        if(!payload?.ok || !payload?.data) throw new Error('invalid support polling response');

        failures=0;
        appendMessages(payload.data.messages||[]);
        applyStatus(payload.data.status||'');
        schedule(3000);
      }catch(_){
        failures++;
        schedule(Math.min(15000,3000+(failures*2000)));
      }finally{
        polling=false;
      }
    };

    document.addEventListener('visibilitychange',()=>{
      if(document.visibilityState==='visible') schedule(150);
    });
    window.addEventListener('focus',()=>schedule(150));
    applyStatus(thread.dataset.supportTicketStatus||'');
    schedule(1200);
  });

  // Modal de detalhes das vagas públicas: mantém o usuário na lista.
  qa('[data-public-job-modal-open]').forEach(button=>{
    button.addEventListener('click',()=>{
      const id=button.getAttribute('data-public-job-modal-open');
      const dialog=id?document.getElementById(id):null;
      if(!(dialog instanceof HTMLDialogElement)) return;
      dialog.showModal();
      document.body.classList.add('public-job-modal-opened');
      const close=q('[data-public-job-modal-close]',dialog);
      window.setTimeout(()=>close?.focus(),0);
    });
  });

  qa('.public-job-modal').forEach(dialog=>{
    if(!(dialog instanceof HTMLDialogElement)) return;

    const closeModal=()=>{
      if(dialog.open) dialog.close();
    };

    qa('[data-public-job-modal-close]',dialog).forEach(button=>{
      button.addEventListener('click',closeModal);
    });

    dialog.addEventListener('click',event=>{
      if(event.target!==dialog) return;
      const rect=dialog.getBoundingClientRect();
      const inside=event.clientX>=rect.left&&event.clientX<=rect.right
        &&event.clientY>=rect.top&&event.clientY<=rect.bottom;
      if(!inside) closeModal();
    });

    dialog.addEventListener('close',()=>{
      document.body.classList.remove('public-job-modal-opened');
    });
  });

  // Distância de vagas pela localização real do navegador.
  const deg2rad=value=>value*(Math.PI/180);
  const haversineKm=(lat1,lng1,lat2,lng2)=>{
    const earth=6371;
    const dLat=deg2rad(lat2-lat1);
    const dLng=deg2rad(lng2-lng1);
    const a=Math.sin(dLat/2)**2+Math.cos(deg2rad(lat1))*Math.cos(deg2rad(lat2))*Math.sin(dLng/2)**2;
    return earth*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
  };
  const formatDistance=km=>{
    if(!Number.isFinite(km)) return 'Distância indisponível';
    if(km<1) return Math.max(1,Math.round(km*1000))+' m';
    return (km<10?km.toFixed(1):Math.round(km))+' km';
  };

  let browserLocationPromise=null;
  const getBrowserLocation=(force=false)=>{
    if(force) browserLocationPromise=null;
    if(browserLocationPromise) return browserLocationPromise;
    browserLocationPromise=new Promise((resolve,reject)=>{
      if(!navigator.geolocation){
        reject(new Error('Seu navegador não oferece geolocalização.'));
        return;
      }
      navigator.geolocation.getCurrentPosition(
        position=>resolve({
          lat:Number(position.coords.latitude),
          lng:Number(position.coords.longitude),
          accuracy:Number(position.coords.accuracy||0)
        }),
        error=>{
          const message=error?.code===1
            ? 'A localização foi bloqueada. Clique no cadeado ao lado do endereço do site e permita Localização.'
            : (error?.code===2?'Não foi possível determinar sua localização agora.':'A localização demorou para responder. Tente novamente.');
          reject(new Error(message));
        },
        {enableHighAccuracy:true,timeout:15000,maximumAge:300000}
      );
    });
    return browserLocationPromise;
  };

  let geocodeQueue=Promise.resolve();
  const geocodeAddress=query=>{
    const normalized=(query||'').replace(/\s+/g,' ').trim();
    if(!normalized) return Promise.resolve(null);
    const key='tp-geocode:'+normalized.toLocaleLowerCase('pt-BR');
    try{
      const cached=JSON.parse(localStorage.getItem(key)||'null');
      if(cached && Number.isFinite(Number(cached.lat)) && Number.isFinite(Number(cached.lng))){
        return Promise.resolve({lat:Number(cached.lat),lng:Number(cached.lng)});
      }
    }catch(_){}

    const task=async()=>{
      try{
        const url='https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=br&q='+encodeURIComponent(normalized);
        const response=await fetch(url,{headers:{'Accept':'application/json'},cache:'force-cache'});
        if(!response.ok) return null;
        const data=await response.json();
        const first=Array.isArray(data)?data[0]:null;
        if(!first) return null;
        const result={lat:Number(first.lat),lng:Number(first.lon)};
        if(!Number.isFinite(result.lat)||!Number.isFinite(result.lng)) return null;
        try{localStorage.setItem(key,JSON.stringify(result));}catch(_){}
        return result;
      }catch(_){
        return null;
      }finally{
        await new Promise(resolve=>window.setTimeout(resolve,1050));
      }
    };
    const queued=geocodeQueue.then(task,task);
    geocodeQueue=queued.catch(()=>null);
    return queued;
  };

  const resolveOpportunityCoordinates=async row=>{
    const lat=Number(row.dataset.shiftLat);
    const lng=Number(row.dataset.shiftLng);
    if(Number.isFinite(lat)&&Number.isFinite(lng)&&Math.abs(lat)<=90&&Math.abs(lng)<=180&&(lat!==0||lng!==0)){
      return {lat,lng};
    }

    const address=(row.dataset.shiftAddress||'').trim();
    const city=(row.dataset.shiftCity||'').trim();
    const state=(row.dataset.shiftState||'').trim();
    const exact=[address,city,state,'Brasil'].filter(Boolean).join(', ');
    let found=await geocodeAddress(exact);
    if(!found && city) found=await geocodeAddress([city,state,'Brasil'].filter(Boolean).join(', '));
    if(found){
      row.dataset.shiftLat=String(found.lat);
      row.dataset.shiftLng=String(found.lng);
    }
    return found;
  };

  const roadDistanceCache=new Map();
  const routeCacheKey=(origin,destination)=>[
    origin.lat.toFixed(5),origin.lng.toFixed(5),
    destination.lat.toFixed(5),destination.lng.toFixed(5)
  ].join(',');

  const valhallaRoadDistancesKm=async(origin,items)=>{
    const results=new Map();
    if(!items.length) return results;

    const batchSize=20;
    for(let start=0;start<items.length;start+=batchSize){
      const batch=items.slice(start,start+batchSize);
      const payload={
        sources:[{lat:origin.lat,lon:origin.lng}],
        targets:batch.map(item=>({lat:item.destination.lat,lon:item.destination.lng})),
        costing:'auto',
        units:'km',
        costing_options:{
          auto:{
            use_highways:0.8,
            use_tracks:0,
            use_living_streets:0,
            exclude_unpaved:true,
            use_tolls:0.5
          }
        }
      };

      try{
        const url='https://valhalla1.openstreetmap.de/sources_to_targets?json='+encodeURIComponent(JSON.stringify(payload));
        const response=await fetch(url,{headers:{'Accept':'application/json'},cache:'no-store'});
        if(!response.ok) continue;
        const data=await response.json();
        const matrix=data?.sources_to_targets;

        if(Array.isArray(matrix)&&Array.isArray(matrix[0])){
          matrix[0].forEach((entry,index)=>{
            const km=Number(entry?.distance);
            if(Number.isFinite(km)&&km>=0&&batch[index]) results.set(batch[index].row,km);
          });
          continue;
        }

        const distances=matrix?.distances;
        if(Array.isArray(distances)&&Array.isArray(distances[0])){
          distances[0].forEach((value,index)=>{
            const km=Number(value);
            if(Number.isFinite(km)&&km>=0&&batch[index]) results.set(batch[index].row,km);
          });
        }
      }catch(_){}
    }
    return results;
  };

  const osrmRoadDistancesKm=async(origin,items)=>{
    const results=new Map();
    if(!items.length) return results;

    const batchSize=20;
    for(let start=0;start<items.length;start+=batchSize){
      const batch=items.slice(start,start+batchSize);
      const coordinates=[origin,...batch.map(item=>item.destination)]
        .map(point=>point.lng+','+point.lat)
        .join(';');
      const destinations=batch.map((_,index)=>index+1).join(';');
      const url='https://router.project-osrm.org/table/v1/driving/'+coordinates
        +'?sources=0&destinations='+destinations+'&annotations=distance';

      try{
        const response=await fetch(url,{headers:{'Accept':'application/json'},cache:'no-store'});
        if(!response.ok) continue;
        const payload=await response.json();
        const distances=Array.isArray(payload?.distances?.[0])?payload.distances[0]:[];
        batch.forEach((item,index)=>{
          const meters=Number(distances[index]);
          if(Number.isFinite(meters)&&meters>=0) results.set(item.row,meters/1000);
        });
      }catch(_){}
    }
    return results;
  };

  const roadDistancesKm=async(origin,items)=>{
    const results=new Map();
    const pending=[];

    items.forEach(item=>{
      const key=routeCacheKey(origin,item.destination);
      let cached=roadDistanceCache.get(key);
      if(cached===undefined){
        try{
          const stored=sessionStorage.getItem('tp-road:'+key);
          cached=stored===null?undefined:Number(stored);
        }catch(_){}
      }
      if(Number.isFinite(cached)){
        results.set(item.row,cached);
      }else{
        pending.push({...item,key});
      }
    });

    // Valhalla tende a evitar atalhos por vias não pavimentadas e fica mais
    // próximo do comportamento esperado para deslocamento de carro.
    const valhalla=await valhallaRoadDistancesKm(origin,pending);
    valhalla.forEach((km,row)=>results.set(row,km));

    const unresolved=pending.filter(item=>!results.has(item.row));
    if(unresolved.length){
      const osrm=await osrmRoadDistancesKm(origin,unresolved);
      osrm.forEach((km,row)=>results.set(row,km));
    }

    pending.forEach(item=>{
      const km=results.get(item.row);
      if(Number.isFinite(km)){
        roadDistanceCache.set(item.key,km);
        try{sessionStorage.setItem('tp-road:'+item.key,String(km));}catch(_){}
      }
    });

    return results;
  };

  const formatAccuracy=meters=>{
    if(!Number.isFinite(meters)||meters<=0) return '';
    if(meters<1000) return '±'+Math.round(meters)+' m';
    return '±'+(meters/1000).toFixed(meters<10000?1:0)+' km';
  };

  qa('[data-opportunity-location]').forEach(root=>{
    const rows=qa('[data-opportunity-row]',root);
    if(!rows.length) return;

    const status=q('[data-location-status]',root);
    const title=q('[data-location-title]',root);
    const requestButtons=qa('[data-location-request]',root);
    const manualInput=q('[data-manual-location-input]',root);
    const manualApply=q('[data-manual-location-apply]',root);
    const manualStatus=q('[data-manual-location-status]',root);
    const search=q('[data-opportunity-search]',root);
    const radius=q('[data-opportunity-radius]',root);
    const days=q('[data-opportunity-days]',root);
    const list=q('[data-opportunity-list]',root)||rows[0]?.parentElement;
    const empty=q('[data-opportunity-empty]',root);
    let userLocation=null;

    const dayMatches=row=>{
      const value=days?.value||'0';
      if(value==='0'||!value) return true;
      const raw=row.dataset.shiftStart||'';
      const start=new Date(raw.replace(' ','T'));
      if(Number.isNaN(start.getTime())) return true;
      const now=new Date();
      const todayStart=new Date(now.getFullYear(),now.getMonth(),now.getDate());
      const shiftStart=new Date(start.getFullYear(),start.getMonth(),start.getDate());
      const delta=Math.round((shiftStart-todayStart)/86400000);
      if(value==='today') return delta===0;
      if(value==='tomorrow') return delta===1;
      const horizon=Number(value);
      return !Number.isFinite(horizon)||horizon<=0 ? true : delta>=0&&delta<horizon;
    };

    const applyFilters=()=>{
      const term=(search?.value||'').trim().toLocaleLowerCase('pt-BR');
      const maxKm=Number(radius?.value||0);
      let visible=0;
      rows.forEach(row=>{
        const hay=(row.dataset.search||row.textContent||'').toLocaleLowerCase('pt-BR');
        const distance=Number(row.dataset.distanceKm);
        const hasDistance=Number.isFinite(distance);
        const okSearch=!term||hay.includes(term);
        const okRadius=!maxKm||!userLocation||!hasDistance||distance<=maxKm;
        const okDay=dayMatches(row);
        row.hidden=!(okSearch&&okRadius&&okDay);
        if(!row.hidden) visible++;
      });
      if(empty) empty.hidden=visible!==0;
    };

    const sortByDistance=()=>{
      if(!list) return;
      [...rows].sort((a,b)=>{
        const da=Number(a.dataset.distanceKm);
        const db=Number(b.dataset.distanceKm);
        const va=Number.isFinite(da)?da:Number.POSITIVE_INFINITY;
        const vb=Number.isFinite(db)?db:Number.POSITIVE_INFINITY;
        return va-vb;
      }).forEach(row=>list.appendChild(row));
    };

    const calculateDistances=async location=>{
      userLocation=location;
      const accuracyText=location.manual?'endereço informado manualmente':formatAccuracy(location.accuracy);
      if(title) title.textContent=location.manual?'Ponto de partida definido manualmente':'Localização autorizada';
      if(status){
        if(location.manual){
          status.textContent='Calculando as distâncias pelas rotas viárias a partir do endereço informado.';
        }else if(Number(location.accuracy)>1000){
          status.textContent='O Chrome forneceu uma localização aproximada ('+accuracyText+'). As distâncias usam rotas viárias, mas você pode informar um endereço manual abaixo para melhorar a origem.';
        }else{
          status.textContent='Calculando as distâncias pelas rotas viárias'+(accuracyText?' com precisão da origem de '+accuracyText+'.':'.');
        }
      }

      const resolved=[];
      for(const row of rows){
        const label=q('[data-opportunity-distance]',row);
        const kind=q('[data-opportunity-distance-kind]',row);
        if(label) label.textContent='Calculando rota…';
        if(kind) kind.textContent='pela rota';
        const destination=await resolveOpportunityCoordinates(row);
        if(!destination){
          row.dataset.distanceKm='';
          row.dataset.distanceMode='';
          if(label) label.textContent='Distância indisponível';
          if(kind) kind.textContent='local';
          continue;
        }
        resolved.push({row,destination});
      }

      const road=await roadDistancesKm(location,resolved);
      resolved.forEach(item=>{
        const label=q('[data-opportunity-distance]',item.row);
        const kind=q('[data-opportunity-distance-kind]',item.row);
        const routeKm=road.get(item.row);
        if(Number.isFinite(routeKm)){
          item.row.dataset.distanceKm=String(routeKm);
          item.row.dataset.distanceMode='road';
          if(label) label.textContent=formatDistance(routeKm);
          if(kind) kind.textContent='pela rota';
          return;
        }

        const straightKm=haversineKm(location.lat,location.lng,item.destination.lat,item.destination.lng);
        item.row.dataset.distanceKm=String(straightKm);
        item.row.dataset.distanceMode='straight';
        if(label) label.textContent=formatDistance(straightKm);
        if(kind) kind.textContent='linha reta';
      });

      sortByDistance();
      applyFilters();
      if(status){
        const routeCount=resolved.filter(item=>item.row.dataset.distanceMode==='road').length;
        const fallbackCount=resolved.filter(item=>item.row.dataset.distanceMode==='straight').length;
        const base=location.manual
          ?'Ponto de partida manual aplicado.'
          :'Localização do navegador aplicada'+(accuracyText?' ('+accuracyText+').':'.');
        status.textContent=base+' '+routeCount+' vaga(s) calculada(s) pela rota rodoviária principal'
          +(fallbackCount?' e '+fallbackCount+' com estimativa em linha reta porque o serviço de rotas não respondeu.':'.');
      }
    };

    const requestLocation=async(force=false)=>{
      requestButtons.forEach(button=>{button.disabled=true;button.textContent='Obtendo localização…';});
      if(status) status.textContent='Aguardando autorização de localização do navegador…';
      try{
        const location=await getBrowserLocation(force);
        await calculateDistances(location);
      }catch(error){
        userLocation=null;
        rows.forEach(row=>{
          const label=q('[data-opportunity-distance]',row);
          if(label) label.textContent='Permita a localização';
        });
        if(title) title.textContent='Localização necessária para calcular distância';
        if(status) status.textContent=error?.message||'Não foi possível acessar sua localização.';
        applyFilters();
      }finally{
        requestButtons.forEach(button=>{button.disabled=false;button.textContent='Atualizar localização';});
      }
    };

    search?.addEventListener('input',applyFilters);
    radius?.addEventListener('change',applyFilters);
    days?.addEventListener('change',applyFilters);
    requestButtons.forEach(button=>button.addEventListener('click',()=>requestLocation(true)));

    manualApply?.addEventListener('click',async()=>{
      const address=(manualInput?.value||'').trim();
      if(address.length<5){
        if(manualStatus) manualStatus.textContent='Informe rua, número e cidade para localizar o ponto de partida.';
        manualInput?.focus();
        return;
      }
      manualApply.disabled=true;
      if(manualStatus) manualStatus.textContent='Localizando o endereço informado…';
      try{
        let origin=await geocodeAddress(address);
        if(!origin && !/brasil/i.test(address)) origin=await geocodeAddress(address+', Brasil');
        if(!origin){
          if(manualStatus) manualStatus.textContent='Não consegui localizar esse endereço. Inclua número, cidade e UF e tente novamente.';
          return;
        }
        if(manualStatus) manualStatus.textContent='Endereço localizado. Calculando as rotas…';
        await calculateDistances({...origin,accuracy:0,manual:true});
        if(manualStatus) manualStatus.textContent='Endereço manual em uso para o cálculo das distâncias.';
      }finally{
        manualApply.disabled=false;
      }
    });
    manualInput?.addEventListener('keydown',event=>{
      if(event.key==='Enter'){
        event.preventDefault();
        manualApply?.click();
      }
    });

    applyFilters();
    requestLocation(false);
  });

  // Geocodifica o endereço da vaga ao criar/editar para que as próximas consultas
  // não dependam de geocodificação em tempo real.
  qa('form[data-shift-form]').forEach(form=>{
    const address=q('input[name="address"]',form);
    const city=q('input[name="city"]',form);
    const state=q('input[name="state"]',form);
    const lat=q('[data-shift-latitude]',form);
    const lng=q('[data-shift-longitude]',form);
    const status=q('[data-shift-geocode-status]',form);
    let timer=null;
    let resolving=false;

    const queryText=()=>[address?.value,city?.value,state?.value,'Brasil'].filter(Boolean).join(', ');
    const clearCoordinates=()=>{
      if(lat) lat.value='';
      if(lng) lng.value='';
      if(status) status.textContent='Endereço alterado. As coordenadas serão atualizadas automaticamente.';
    };
    const resolveAddress=async()=>{
      if(resolving) return null;
      const query=queryText().trim();
      if(query.length<8) return null;
      resolving=true;
      if(status) status.textContent='Localizando o endereço da vaga…';
      try{
        const result=await geocodeAddress(query);
        if(result){
          if(lat) lat.value=String(result.lat);
          if(lng) lng.value=String(result.lng);
          if(status) status.textContent='Localização da vaga encontrada. O cálculo de distância ficará disponível aos profissionais.';
        }else if(status){
          status.textContent='Não foi possível localizar este endereço automaticamente. A vaga poderá ser salva e o sistema tentará novamente na tela de oportunidades.';
        }
        return result;
      }finally{
        resolving=false;
      }
    };

    [address,city,state].forEach(input=>{
      input?.addEventListener('input',()=>{
        clearCoordinates();
        window.clearTimeout(timer);
        timer=window.setTimeout(resolveAddress,900);
      });
      input?.addEventListener('blur',resolveAddress);
    });

    form.addEventListener('submit',async event=>{
      if(form.dataset.shiftSubmitReady==='1') return;
      if((lat?.value||'')!==''&&(lng?.value||'')!=='') return;
      event.preventDefault();
      const submit=q('button[type="submit"]',form);
      if(submit) submit.disabled=true;
      await resolveAddress();
      form.dataset.shiftSubmitReady='1';
      if(submit) submit.disabled=false;
      form.submit();
    });
  });

})();
