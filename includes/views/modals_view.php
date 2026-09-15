        <!-- ==========================================
             NATIVE MODAL 1: TÙY CHỈNH EMAIL
             ========================================== -->
        <div id="customEmailModal" class="tmail-modal-overlay" aria-hidden="true">
            <div class="tmail-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="customEmailModalTitle">
                <div class="tmail-modal-header">
                    <h3 id="customEmailModalTitle" class="tmail-modal-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <span>Tùy chỉnh email</span>
                    </h3>
                    <button type="button" class="tmail-modal-close-btn" id="customEmailCloseBtn" aria-label="Đóng">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="tmail-modal-body">
                    <div class="tmail-modal-form-group">
                        <label class="tmail-modal-label" for="customEmailPrefixInput">Tên hòm thư mong muốn</label>
                        <div style="display: flex; align-items: stretch; gap: 8px;">
                            <input type="text" id="customEmailPrefixInput" class="tmail-modal-input" placeholder="ví dụ: tester, phuc710" style="flex: 1.2;" autocomplete="off" spellcheck="false">
                            <select id="customEmailDomainSelect" class="tmail-modal-select" style="flex: 1;">
                                <?php foreach ($activeDomains as $d): ?>
                                    <option value="<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?>">@<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                                <option value="__add_custom_domain__" style="font-weight: 700; color: #0284c7;">+ Add Domain ...</option>
                            </select>
                        </div>
                    </div>
                    <div class="tmail-modal-preview" id="customEmailPreviewText">
                        Email sẽ tạo: <span id="customEmailPreviewVal">...</span>
                    </div>
                    <div id="customEmailAlert" class="tmail-modal-alert alert-error" style="display: none; margin-top: 12px;"></div>
                </div>
                <div class="tmail-modal-footer">
                    <button type="button" class="tmail-modal-btn tmail-modal-btn-ghost" id="customEmailCancelBtn">Hủy</button>
                    <button type="button" class="tmail-modal-btn tmail-modal-btn-primary" id="customEmailSubmitBtn">
                        <span>Tạo mới</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ==========================================
             NATIVE MODAL 2: THÊM TÊN (CLOUDFLARE)
             ========================================== -->
        <div id="addDomainModal" class="tmail-modal-overlay" aria-hidden="true">
            <div class="tmail-modal-dialog modal-dialog-wide" role="dialog" aria-modal="true" aria-labelledby="addDomainModalTitle">
                <div class="tmail-modal-header">
                    <h3 id="addDomainModalTitle" class="tmail-modal-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <span id="addDomainTitleText">Thêm tên miền riêng (Cloudflare)</span>
                    </h3>
                    <button type="button" class="tmail-modal-close-btn" id="addDomainCloseBtn" aria-label="Đóng">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                
                <!-- STEP 1: NHẬP TÊN MIỀN -->
                <div id="addDomainStep1" class="tmail-modal-body">
                    <p style="font-size: 13.5px; color: #475569; line-height: 1.6; margin: 0 0 14px 0;">
                        Sử dụng tên miền riêng của bạn qua <strong>Cloudflare Email Routing</strong> để nhận email không giới hạn.
                    </p>
                    <div class="tmail-modal-form-group">
                        <label class="tmail-modal-label" for="addDomainInputVal">Tên miền của bạn (Domain / Subdomain)</label>
                        <input type="text" id="addDomainInputVal" class="tmail-modal-input" placeholder="ví dụ: devmail.vn, dewii.dpdns.org" autocomplete="off" spellcheck="false" style="font-family: 'JetBrains Mono', monospace;">
                    </div>
                    <div id="addDomainStep1Alert" class="tmail-modal-alert alert-error" style="display: none;"></div>
                </div>
                <div id="addDomainStep1Footer" class="tmail-modal-footer">
                    <button type="button" class="tmail-modal-btn tmail-modal-btn-ghost" id="addDomainStep1CancelBtn">Đóng</button>
                    <button type="button" class="tmail-modal-btn tmail-modal-btn-accent" id="addDomainStep1NextBtn">
                        <span>Tiếp tục &rarr;</span>
                    </button>
                </div>

                <!-- STEP 2: CẤU HÌNH CLOUDFLARE -->
                <div id="addDomainStep2" class="tmail-modal-body" style="display: none;">
                    <div style="display: flex; align-items: center; justify-content: space-between; background: #f0f9ff; border: 1.5px solid #bae6fd; border-radius: 12px; padding: 12px 16px; margin-bottom: 14px; gap: 12px; flex-wrap: wrap;">
                        <div>
                            <span style="font-size: 11px; font-weight: 700; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px;">Tên miền của bạn:</span>
                            <div id="addDomainStep2DomainBadge" style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #0f172a; font-size: 15px;">@...</div>
                        </div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button type="button" id="addDomainCopyWorkerBtn" class="tmail-modal-btn tmail-modal-btn-ghost" style="padding: 7px 12px; font-size: 13px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                <span>Sao chép Code</span>
                            </button>
                            <a href="#" id="addDomainDownloadWorkerLink" download="cloudflare-worker.js" class="tmail-modal-btn tmail-modal-btn-accent" style="padding: 7px 14px; font-size: 13px; text-decoration: none;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                <span>Tải File worker.js</span>
                            </a>
                        </div>
                    </div>

                    <div class="tmail-modal-alert alert-warning" style="margin-bottom: 12px;">
                        <div>⚡ <em>Do Cloudflare không cho chọn Worker giữa các tài khoản khác nhau, bạn chỉ cần tạo 1 Worker trên Cloudflare của bạn và dán code đã cấp sẵn ở trên.</em></div>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; margin-bottom: 12px;">
                        <div style="margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px dashed #e2e8f0;">
                            <div style="font-weight: 700; color: #0f172a; font-size: 13.5px; margin-bottom: 4px;">
                                Bước 1: Tạo Worker trên tài khoản Cloudflare của bạn
                            </div>
                            <ol style="margin: 0; padding-left: 18px; color: #475569; font-size: 13px; line-height: 1.6;">
                                <li>Vào <a href="https://dash.cloudflare.com/?to=/:account/workers-and-pages" target="_blank" rel="noopener" style="color: #0284c7; font-weight: 600; text-decoration: underline;">Workers & Pages</a> &rarr; Bấm <strong>Create Application</strong> &rarr; <strong>Create Worker</strong> (ví dụ đặt tên: <code>v-bridge</code>) &rarr; Nhấn <strong>Deploy</strong>.</li>
                                <li>Bấm <strong>Edit Code</strong> &rarr; Xóa hết code mẫu &rarr; Bấm nút <strong>"Sao chép Code"</strong> ở trên dán vào &rarr; Nhấn <strong>Deploy</strong>.</li>
                            </ol>
                        </div>

                        <div>
                            <div style="font-weight: 700; color: #0f172a; font-size: 13.5px; margin-bottom: 4px;">
                                Bước 2: Bật Email Routing & Trỏ Catch-all vào Worker
                            </div>
                            <ol style="margin: 0; padding-left: 18px; color: #475569; font-size: 13px; line-height: 1.6;">
                                <li>Truy cập <a href="https://dash.cloudflare.com/?to=/:account/email-service/routing" target="_blank" rel="noopener" style="color: #0284c7; font-weight: 600; text-decoration: underline;">Email Routing</a> tại domain của bạn &rarr; Bấm <strong>Enable Email Routing</strong> để Cloudflare tự thêm bản ghi DNS MX & SPF (Locked xanh).</li>
                                <li>Chuyển sang tab <strong>Routing rules</strong> &rarr; Tại dòng <strong>Catch-all</strong>, nhìn sang bên phải bấm nút <code>...</code> (3 dấu chấm) &rarr; chọn <strong>Edit</strong>.</li>
                                <li>Sửa các trường:
                                    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 6px 10px; margin: 6px 0; font-size: 12.5px; font-family: monospace;">
                                        <div>&bull; Action: <strong>Send to a Worker</strong></div>
                                        <div>&bull; Destination: <strong>v-bridge</strong> (Worker vừa tạo ở Bước 1)</div>
                                        <div>&bull; Status: <strong>Active (Bật xanh)</strong></div>
                                    </div>
                                </li>
                                <li>Bấm <strong>Save</strong>. Khi thấy dòng Catch-all hiển thị chip tên Worker là hoàn tất!</li>
                            </ol>
                        </div>
                    </div>

                    <div id="addDomainStep2Alert" class="tmail-modal-alert alert-error" style="display: none;"></div>
                </div>
                <div id="addDomainStep2Footer" class="tmail-modal-footer" style="display: none;">
                    <button type="button" class="tmail-modal-btn tmail-modal-btn-ghost" id="addDomainStep2BackBtn">Để sau</button>
                    <button type="button" class="tmail-modal-btn tmail-modal-btn-emerald" id="addDomainStep2VerifyBtn">
                        <span>🚀 Kiểm Tra Ngay</span>
                    </button>
                </div>
            </div>
        </div>
