(do
  (def benchmark-arithmetic
    (fn (n)
      (if (= n 0)
          0
          (+ n (benchmark-arithmetic (dec n))))))
  (benchmark-arithmetic 5000))
