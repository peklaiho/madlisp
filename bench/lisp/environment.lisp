(do
  (def benchmark-environment
    (fn (n)
      (if (= n 0)
          0
          (let (a 1 b 2 c 3)
            (+ a b c (benchmark-environment (dec n)))))))
  (benchmark-environment 5000))
